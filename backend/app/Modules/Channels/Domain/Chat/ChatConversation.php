<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Chat;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A conversation while it is live.
 *
 * Four states and no column that stores which one: waiting, taken, ended,
 * abandoned. They are derived from the timestamps that record what actually
 * happened, because a stored state is a second copy of the same fact and the
 * copy is the one that drifts.
 */
final class ChatConversation extends Model
{
    use HasUlids;

    protected $table = 'chat_conversations';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'taken_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'abandoned_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'token_expires_at' => 'immutable_datetime',
        ];
    }

    public function isFinished(): bool
    {
        return $this->ended_at !== null || $this->abandoned_at !== null;
    }

    /** Untaken and unfinished — the only rows on the waiting list. */
    public function isWaiting(): bool
    {
        return $this->taken_by === null && ! $this->isFinished();
    }

    /**
     * Whether the visitor's token still works.
     *
     * A finished conversation is closed to the visitor even inside the token's
     * life: the token is scoped to ONE conversation, and once that conversation
     * is over there is nothing left it grants.
     */
    public function acceptsVisitor(): bool
    {
        return ! $this->isFinished() && $this->token_expires_at->isFuture();
    }

    /** The state, in one word, for a reader who wants to know which it is. */
    public function state(): string
    {
        return match (true) {
            $this->abandoned_at !== null => 'abandoned',
            $this->ended_at !== null => 'ended',
            $this->taken_by !== null => 'taken',
            default => 'waiting',
        };
    }
}
