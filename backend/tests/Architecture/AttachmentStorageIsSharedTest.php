<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every backend process sees the same attachment bytes.
 *
 * The web process receives an upload and writes the file into `quarantine/`.
 * The WORKER picks up `ScanAttachmentJob` and moves it to `clean/`. They are
 * different containers — and with no shared mount each one had its own copy of
 * the directory, baked in from its own image layer.
 *
 * So the worker looked for a file that only ever existed in the web container:
 *
 *   UnableToMoveFile: quarantine/<id> to clean/<id>, because unknown reason
 *
 * Five retries, then failed. The attachment stayed `pending` — which means
 * quarantined, which by design means undownloadable — for ever. **Every file
 * uploaded through the running application was silently lost**, and the
 * interface showed it attached to the message the whole time.
 *
 * Nothing caught it because the seeded attachments work: the seeder runs the
 * scan inline, in the same container that wrote the file, so it never crosses
 * the boundary. And no test runs two containers.
 */
final class AttachmentStorageIsSharedTest extends TestCase
{
    /** The processes that read or write attachment bytes. */
    private const BACKEND_SERVICES = ['backend-web', 'backend-worker', 'backend-scheduler'];

    private const MOUNT = 'attachments:/app/storage/app/attachments';

    /**
     * @return array<string, mixed>
     */
    private function compose(): array
    {
        $path = dirname(__DIR__, 3).'/docker-compose.yml';

        $this->assertFileExists($path);

        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile($path);

        return $parsed;
    }

    public function test_every_backend_process_mounts_the_same_attachment_store(): void
    {
        $services = $this->compose()['services'] ?? [];

        foreach (self::BACKEND_SERVICES as $name) {
            $this->assertArrayHasKey($name, $services, "docker-compose.yml has no [{$name}].");

            $volumes = $services[$name]['volumes'] ?? [];

            $this->assertContains(
                self::MOUNT,
                $volumes,
                "[{$name}] does not mount the shared attachment store, so a file it writes "
                .'is invisible to the process that has to scan or serve it.',
            );
        }
    }

    public function test_the_volume_is_declared(): void
    {
        // Guarding the guard: three services can name a volume that does not
        // exist, and compose would refuse to start rather than fail here.
        $this->assertArrayHasKey('attachments', $this->compose()['volumes'] ?? []);
    }
}
