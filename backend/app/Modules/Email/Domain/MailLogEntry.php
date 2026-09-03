<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One attempt to send or receive an email.
 *
 * @property string $direction
 * @property string $status
 */
final class MailLogEntry extends Model
{
    use HasUlids;

    protected $table = 'mail_log';

    /** Written only by MailLog. */
    protected $guarded = ['*'];

    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
