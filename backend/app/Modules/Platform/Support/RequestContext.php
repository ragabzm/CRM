<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Closure;

/**
 * Per-process ambient context for the request (or console command) in flight.
 *
 * Registered as a container singleton by the PlatformServiceProvider and
 * populated by the AssignRequestId middleware. The Monolog processor and the
 * problem-details handler both read from here, which is why the five logging
 * fields and the problem `trace_id` always agree with each other.
 *
 * Everything is nullable on purpose: a log line emitted before the middleware
 * runs (boot, queue worker, scheduler) must still carry all five keys.
 */
final class RequestContext
{
    public const ACTOR_TYPES = ['user', 'service', 'guest'];

    private ?string $requestId = null;

    private ?string $module = null;

    private ?string $ticketId = null;

    /** @var 'user'|'service'|'guest'|null Set explicitly; overrides the resolver. */
    private ?string $actorType = null;

    private ?string $actorId = null;

    /**
     * The actor is resolved lazily rather than snapshotted: AssignRequestId runs
     * before auth:sanctum, so at middleware time there is no authenticated user
     * yet, but log lines written later in the same request must still name one.
     *
     * @var Closure(): array{0: 'user'|'service'|'guest', 1: string|null}|null
     */
    private ?Closure $actorResolver = null;

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /** @param Closure(): array{0: 'user'|'service'|'guest', 1: string|null} $resolver */
    public function setActorResolver(Closure $resolver): void
    {
        $this->actorResolver = $resolver;
    }

    /** @param 'user'|'service'|'guest' $actorType */
    public function setActor(string $actorType, ?string $actorId): void
    {
        $this->actorType = $actorType;
        $this->actorId = $actorId;
    }

    /** @return array{0: 'user'|'service'|'guest', 1: string|null} */
    public function actor(): array
    {
        if ($this->actorType !== null) {
            return [$this->actorType, $this->actorId];
        }

        if ($this->actorResolver !== null) {
            return ($this->actorResolver)();
        }

        return ['guest', null];
    }

    public function module(): ?string
    {
        return $this->module;
    }

    public function setModule(?string $module): void
    {
        $this->module = $module;
    }

    public function ticketId(): ?string
    {
        return $this->ticketId;
    }

    public function setTicketId(?string $ticketId): void
    {
        $this->ticketId = $ticketId;
    }

    /**
     * The five fields every structured log line must carry.
     *
     * @return array{request_id: string|null, actor_type: string, actor_id: string|null, module: string|null, ticket_id: string|null}
     */
    public function toLogFields(): array
    {
        [$actorType, $actorId] = $this->actor();

        return [
            'request_id' => $this->requestId,
            'actor_type' => $this->actorType ?? $actorType,
            'actor_id' => $actorId,
            'module' => $this->module,
            'ticket_id' => $this->ticketId,
        ];
    }
}
