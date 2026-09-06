<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Channels\Adapters\PhoneChannelAdapter;
use PHPUnit\Framework\TestCase;

/**
 * WhatsApp and SMS stay one implementation with two configurations.
 *
 * The story's claim, made structural. It is an easy one to lose: the first
 * time WhatsApp needs a template and SMS does not, the obvious move is an
 * `if ($channel === 'whatsapp')` — and from there the two channels have two
 * code paths, two sets of bugs and two places to fix each one.
 *
 * What must stay true is that the only thing distinguishing them is data: a
 * row in `channel_accounts`, and which `ContactKind` a sender's number is
 * recorded under.
 */
final class OneTransportShapeTest extends TestCase
{
    public function test_there_is_one_adapter_serving_both_channels(): void
    {
        $adapters = [];

        foreach (SourceScanner::phpFiles('app/Modules/Channels/Adapters') as $file) {
            $adapters[] = basename($file);
        }

        sort($adapters);

        /*
         * Pinned. A `WhatsAppChannelAdapter.php` beside a `SmsChannelAdapter.php`
         * is the shape this test exists to prevent — and it would look like
         * tidy separation right up until they diverged.
         */
        $this->assertSame(
            [
                // One adapter per TRANSPORT, and chat is its own transport: a
                // widget that polls is not a webhook and not a form post.
                'ChatChannelAdapter.php',
                'PhoneChannelAdapter.php',
                'WebFormChannelAdapter.php',
            ],
            $adapters,
        );
    }

    public function test_the_adapter_does_not_branch_on_which_channel_it_is(): void
    {
        $code = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Channels/Adapters/PhoneChannelAdapter.php'),
        );

        /*
         * ONE comparison against the channel is allowed, and only one: which
         * `ContactKind` a sender's number is recorded under, because a
         * WhatsApp number genuinely is a different identifier from a phone
         * number on the same record. Everything else must be configuration.
         */
        $this->assertSame(
            1,
            substr_count($code, 'self::WHATSAPP'),
            'The adapter branches on the channel more than once. Whatever the new branch is, it belongs in the channel account.',
        );

        $this->assertStringNotContainsString('self::SMS ===', $code);
    }

    public function test_the_send_job_and_the_sender_know_nothing_about_either_channel(): void
    {
        foreach ([
            'app/Modules/Channels/Jobs/SendChannelMessageJob.php',
            'app/Modules/Channels/Domain/Outbound/ChannelSender.php',
        ] as $path) {
            $code = SourceScanner::codeOnly(SourceScanner::basePath($path));

            foreach (['whatsapp', 'sms', 'WhatsApp', 'Sms'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    basename($path).' names a channel. The retry policy, the timeout and the delivery states are shared — a mention of one channel here is the start of two.',
                );
            }
        }
    }

    public function test_both_channels_are_the_same_class(): void
    {
        $whatsapp = new PhoneChannelAdapter(PhoneChannelAdapter::WHATSAPP);
        $sms = new PhoneChannelAdapter(PhoneChannelAdapter::SMS);

        $this->assertSame($whatsapp::class, $sms::class);
        $this->assertSame('whatsapp', $whatsapp->channel());
        $this->assertSame('sms', $sms->channel());
    }
}
