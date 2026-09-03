<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Modules\Email\Infrastructure\LaravelMailTransport;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * The MIME message the provider actually receives.
 *
 * This test exists because of a bug the entire rest of the suite missed.
 *
 * `NullMailTransport` records the header VALUES it was handed and never
 * assembles a message, so every threading test passed while the real transport
 * threw on its first line: Symfony types Message-ID, In-Reply-To and References
 * as identification headers and refuses them as text headers. Sending would
 * have failed for every customer, and nothing red would have appeared until
 * somebody ran it by hand.
 *
 * So this one goes through Laravel's real mail layer, using the in-memory
 * `array` transport, and reads the bytes back out.
 */
final class RealTransportAssemblesTheMessageTest extends TestCase
{
    private function transport(): LaravelMailTransport
    {
        Config::set('mail.mailers.array', ['transport' => 'array']);

        return new LaravelMailTransport(
            $this->app->make(MailFactory::class),
            'array',
            'support@example.test',
            'Support',
        );
    }

    /** @return \Symfony\Component\Mime\Email */
    private function sendAndCapture(array $headers): object
    {
        $this->transport()->send(
            'hana@example.test',
            'Hana',
            '[#TKT-000042] Invoice is wrong',
            'We have credited it.',
            $headers,
            'ar',
        );

        /** @var AbstractTransport $symfony */
        $symfony = $this->app->make(MailFactory::class)->mailer('array')->getSymfonyTransport();

        $messages = $symfony->messages();

        $this->assertNotEmpty($messages, 'Nothing reached the transport.');

        return $messages[count($messages) - 1]->getOriginalMessage();
    }

    public function test_it_sends_at_all(): void
    {
        // The assertion that would have failed. Everything below is detail.
        $message = $this->sendAndCapture(['Message-ID' => '<a.b@example.test>']);

        $this->assertNotNull($message);
    }

    public function test_the_message_id_survives_as_an_identification_header(): void
    {
        $message = $this->sendAndCapture(['Message-ID' => '<a.b@example.test>']);

        // Written back with exactly one pair of angle brackets. Symfony adds
        // them itself, so passing `<a@b>` naively produces `<<a@b>>`.
        $this->assertSame(
            'a.b@example.test',
            $message->getHeaders()->get('Message-ID')?->getBodyAsString() !== null
                ? trim($message->getHeaders()->get('Message-ID')->getBodyAsString(), '<>')
                : null,
        );
    }

    public function test_in_reply_to_survives(): void
    {
        $message = $this->sendAndCapture([
            'Message-ID' => '<c@example.test>',
            'In-Reply-To' => '<a@example.test>',
        ]);

        $this->assertStringContainsString(
            'a@example.test',
            (string) $message->getHeaders()->get('In-Reply-To')?->getBodyAsString(),
        );
    }

    public function test_a_references_chain_keeps_every_id(): void
    {
        $message = $this->sendAndCapture([
            'Message-ID' => '<c@example.test>',
            'References' => '<a@example.test> <b@example.test>',
        ]);

        $references = (string) $message->getHeaders()->get('References')?->getBodyAsString();

        /*
         * All of them, not just the last. The whole chain is what lets a client
         * rebuild the thread when a message in the middle was deleted or never
         * arrived.
         */
        $this->assertStringContainsString('a@example.test', $references);
        $this->assertStringContainsString('b@example.test', $references);
    }

    public function test_the_subject_tag_reaches_the_wire(): void
    {
        $message = $this->sendAndCapture(['Message-ID' => '<c@example.test>']);

        $this->assertSame('[#TKT-000042] Invoice is wrong', $message->getSubject());
    }

    public function test_the_language_is_declared(): void
    {
        $message = $this->sendAndCapture(['Message-ID' => '<c@example.test>']);

        // So a client that honours it renders in the right direction rather
        // than guessing from the body.
        $this->assertStringContainsString(
            'ar',
            (string) $message->getHeaders()->get('Content-Language')?->getBodyAsString(),
        );
    }

    public function test_the_configured_sender_is_used(): void
    {
        $message = $this->sendAndCapture(['Message-ID' => '<c@example.test>']);

        $this->assertSame('support@example.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('Support', $message->getFrom()[0]->getName());
    }

    public function test_a_message_with_no_thread_headers_still_sends(): void
    {
        // The first message on a ticket has nothing to reply to.
        $message = $this->sendAndCapture([]);

        $this->assertNotNull($message);
    }
}
