<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One thing said on a ticket.
 *
 * @property string $body
 * @property string $direction
 */
final class TicketMessage extends Model
{
    use HasUlids;

    protected $table = 'ticket_messages';

    /** Written only by the AppendMessage command. */
    protected $guarded = ['*'];

    /** How much of the body the timeline shows before it is opened. */
    public const PREVIEW_LENGTH = 140;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The first line or so, for a list that must not ship whole message bodies.
     *
     * Trimmed on a word boundary where there is one: cutting mid-word reads as
     * corruption rather than as truncation.
     */
    public static function preview(string $body): string
    {
        $flat = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if (mb_strlen($flat) <= self::PREVIEW_LENGTH) {
            return $flat;
        }

        $cut = mb_substr($flat, 0, self::PREVIEW_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false && $lastSpace > 40 ? mb_substr($cut, 0, $lastSpace) : $cut).'…';
    }
}
