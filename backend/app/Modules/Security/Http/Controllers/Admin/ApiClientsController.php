<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Security\Domain\Api\ApiTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The other systems that talk to this one.
 *
 * THE TOKEN IS SHOWN ONCE. It appears in the response that created it and
 * nowhere else — not in the list, not behind a reveal control, not masked with
 * the last four characters showing, and not in the audit entry that records
 * the issue. There is no code path in this controller that could return it a
 * second time, which is a stronger promise than a rule about who may ask.
 *
 * Revocation is a DELETE of the row, so it takes effect on the client's very
 * next request: Sanctum looks the token up on every call, and a deleted row is
 * a token that authenticates nobody. Not a cache expiry, not a flag checked at
 * the next deploy.
 */
final class ApiClientsController extends Controller
{
    public function __construct(
        private readonly ApiTokens $tokens,
        private readonly AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        $clients = DB::table('personal_access_tokens')
            ->leftJoin('users', function ($join): void {
                $join->on('users.id', '=', 'personal_access_tokens.tokenable_id')
                    ->where('personal_access_tokens.tokenable_type', '=', User::class);
            })
            ->orderByDesc('personal_access_tokens.created_at')
            ->get([
                'personal_access_tokens.id',
                'personal_access_tokens.name',
                'personal_access_tokens.abilities',
                'personal_access_tokens.last_used_at',
                'personal_access_tokens.created_at',
                'users.name as owner',
            ]);

        return new JsonResponse([
            'data' => $clients->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'abilities' => json_decode((string) $row->abilities, true) ?: [],
                'owner' => $row->owner === null ? null : (string) $row->owner,
                /*
                 * When it was last used, which is the only question anybody
                 * asks of this list: a client nothing has called for six months
                 * is a credential somebody should revoke.
                 */
                'last_used_at' => $row->last_used_at === null ? null : (string) $row->last_used_at,
                'created_at' => (string) $row->created_at,
                // No token, no prefix, no last-four. There is nothing here to
                // reveal, which is why there is no reveal control.
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string'],
        ]);

        $owner = User::query()->findOrFail((int) $data['owner_id']);

        $issued = $this->tokens->issue(
            $owner,
            trim((string) $data['name']),
            array_map(strval(...), (array) $data['abilities']),
        );

        $this->audit->record(
            AuditAction::ApiClientIssued,
            targetType: 'api_client',
            targetId: (string) $issued['id'],
            before: null,
            /*
             * The abilities and the owner — never the token. An audit entry is
             * read by more people than the response was, and a secret in it is
             * a secret in a table with its own retention and its own export.
             */
            after: [
                'name' => $issued['name'],
                'abilities' => $issued['abilities'],
                'owner' => $owner->name,
            ],
        );

        return new JsonResponse([
            'data' => [
                'id' => $issued['id'],
                'name' => $issued['name'],
                'abilities' => $issued['abilities'],
                /*
                 * The ONLY time this value exists outside the client's own
                 * configuration. Copy it now; it cannot be recovered.
                 */
                'token' => $issued['token'],
            ],
        ], 201);
    }

    public function destroy(int $client): JsonResponse
    {
        $row = DB::table('personal_access_tokens')->where('id', $client)->first(['id', 'name', 'abilities']);

        if ($row === null) {
            throw ProblemException::make(
                'security.client_not_found',
                'Client not found',
                404,
                "No API client with id [{$client}].",
            );
        }

        DB::table('personal_access_tokens')->where('id', $client)->delete();

        $this->audit->record(
            AuditAction::ApiClientRevoked,
            targetType: 'api_client',
            targetId: (string) $client,
            before: [
                'name' => (string) $row->name,
                'abilities' => json_decode((string) $row->abilities, true) ?: [],
            ],
            after: null,
        );

        return new JsonResponse(null, 204);
    }
}
