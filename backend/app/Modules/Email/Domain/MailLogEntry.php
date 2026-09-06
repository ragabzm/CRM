<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

use App\Modules\Integrations\Domain\ExchangeLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One attempt to send or receive an email — Email's view of the shared log.
 *
 * The rows live in `integration_exchanges` with every other outside call the
 * product makes, because there is one exchange log and not one per
 * integration. What this class adds is EMAIL'S VOCABULARY over it: the generic
 * `target` is an address, the generic `context` holds the message id and the
 * subject, and a `succeeded` exchange is a `sent` email.
 *
 * That translation lives here rather than in the shared writer so the shared
 * log never has to know what a subject line is — the next integration will
 * have a different five fields and none of them will be these.
 *
 * @property string $direction
 * @property string $status
 */
final class MailLogEntry extends Model
{
    use HasUlids;

    protected $table = 'integration_exchanges';

    /** Written only by MailLog. */
    protected $guarded = ['*'];

    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    public const QUEUED = 'queued';

    /**
     * Email's word for a successful exchange.
     *
     * Presentation, not storage: the row says `succeeded` like every other row
     * in the log. This constant is what the API and the console keep saying,
     * so folding the mail log into the exchange log changed no contract that
     * anybody outside this module could see.
     */
    public const SENT = 'sent';

    public const FAILED = 'failed';

    /**
     * Email's rows in the shared log, and nothing else.
     *
     * EXPLICIT rather than a global scope, which this codebase refuses on the
     * grounds that an invisible filter silently truncates exports, reports and
     * admin tooling that legitimately want every row. The cost of being
     * explicit is that a caller can forget — so the thing that would actually
     * hurt, the prune, has a test proving it leaves other integrations alone.
     */
    public static function mail(): Builder
    {
        return static::query()->where('integration', MailLog::INTEGRATION);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'attempt' => 'integer',
            'duration_ms' => 'integer',
            'context' => 'array',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    /** The generic column, under the name mail gives it. */
    public function getAddressAttribute(): string
    {
        return (string) $this->attributes['target'];
    }

    public function getStatusAttribute(string $stored): string
    {
        return $stored === ExchangeLog::SUCCEEDED ? self::SENT : $stored;
    }

    public function getProviderAttribute(): string
    {
        return (string) ($this->context['provider'] ?? '');
    }

    public function getSubjectAttribute(): ?string
    {
        return $this->contextString('subject');
    }

    public function getMessageIdAttribute(): ?string
    {
        return $this->contextString('message_id');
    }

    public function getTicketIdAttribute(): ?string
    {
        return $this->contextString('ticket_id');
    }

    public function getProviderCodeAttribute(): ?string
    {
        return $this->contextString('provider_code');
    }

    /**
     * Email's word in, the log's word out.
     *
     * The console filters by `sent`; the column holds `succeeded`. Translating
     * here keeps that a detail of this module rather than something every
     * caller has to remember.
     */
    public static function storedStatus(string $emailWord): string
    {
        return $emailWord === self::SENT ? ExchangeLog::SUCCEEDED : $emailWord;
    }

    private function contextString(string $key): ?string
    {
        $context = $this->context;

        if (! is_array($context) || ! isset($context[$key]) || ! is_string($context[$key])) {
            return null;
        }

        return $context[$key];
    }
}
