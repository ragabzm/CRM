<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Infrastructure;

use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Domain\Scanning\ScannerUnreachable;
use App\Modules\Platform\Attachments\Domain\Scanning\ScanResult;

/**
 * Streams a file to a clamd daemon over its INSTREAM protocol.
 *
 * INSTREAM rather than the SCAN command, because SCAN asks the daemon to open
 * a path — which only works when clamd shares a filesystem with the
 * application. Streaming works whether clamd is a sidecar, another host, or a
 * container that has never seen our storage volume.
 *
 * Not exercised by the test suite: it needs a real daemon. What IS covered is
 * everything around it — the state machine, the quarantine, the download gate —
 * because those go through the FileScanner port and a fake answers just as well.
 */
final class ClamavFileScanner implements FileScanner
{
    public function __construct(
        private readonly string $socket,
        private readonly int $timeoutSeconds,
        private readonly int $chunkBytes,
    ) {}

    public function scan(string $absolutePath): ScanResult
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            // Our own file we cannot read: not the scanner's fault, but the
            // outcome is the same — we did not check it.
            throw new ScannerUnreachable("Could not open [{$absolutePath}] to scan.");
        }

        $socket = @stream_socket_client(
            $this->socketUri(),
            $errorCode,
            $errorMessage,
            $this->timeoutSeconds,
        );

        if ($socket === false) {
            fclose($handle);

            throw new ScannerUnreachable("clamd unreachable at [{$this->socket}]: {$errorMessage} ({$errorCode})");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $response = $this->stream($socket, $handle);
        } finally {
            fclose($handle);
            fclose($socket);
        }

        return $this->interpret($response);
    }

    private function socketUri(): string
    {
        // A path is a unix socket; anything with a colon is host:port.
        return str_contains($this->socket, ':') ? "tcp://{$this->socket}" : "unix://{$this->socket}";
    }

    /**
     * @param  resource  $socket
     * @param  resource  $file
     */
    private function stream($socket, $file): string
    {
        // zINSTREAM: the trailing NUL makes clamd's parser terminate the
        // command explicitly rather than guessing at a newline.
        $this->write($socket, "zINSTREAM\0");

        while (! feof($file)) {
            $chunk = fread($file, $this->chunkBytes);

            if ($chunk === false || $chunk === '') {
                break;
            }

            // Each chunk is length-prefixed, big-endian.
            $this->write($socket, pack('N', strlen($chunk)).$chunk);
        }

        // A zero-length chunk ends the stream.
        $this->write($socket, pack('N', 0));

        $response = stream_get_contents($socket);

        if ($response === false || trim($response) === '') {
            // A silent socket is indistinguishable from a dead one, and both
            // mean the file was not checked.
            throw new ScannerUnreachable('clamd closed the connection without answering.');
        }

        return trim($response);
    }

    /** @param resource $socket */
    private function write($socket, string $payload): void
    {
        if (@fwrite($socket, $payload) === false) {
            throw new ScannerUnreachable('Writing to clamd failed mid-stream.');
        }
    }

    private function interpret(string $response): ScanResult
    {
        $raw = ['response' => $response];

        if (str_ends_with($response, 'OK') && ! str_contains($response, 'FOUND')) {
            return ScanResult::clean($raw);
        }

        if (str_contains($response, 'FOUND')) {
            // "stream: Eicar-Test-Signature FOUND" — the signature name is the
            // reason worth showing.
            $signature = trim(str_replace(['stream:', 'FOUND'], '', $response));

            return ScanResult::failed($signature === '' ? 'Malware detected' : $signature, $raw);
        }

        /*
         * ERROR, or anything unrecognised. NOT a failed scan: clamd saying it
         * ran out of memory is not a verdict about this file, and recording it
         * as one would tell a customer their invoice contains a virus.
         */
        throw new ScannerUnreachable("clamd returned an unusable response: {$response}");
    }
}
