<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Security\Http\Requests\StoreUserRequest;
use App\Modules\Security\Http\Requests\UpdateUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Administrator management of staff accounts.
 *
 * Every route here sits behind `can.capability:user.manage`; the middleware is
 * the enforcement, and it runs whether or not a UI ever hid the button.
 */
final class UsersController extends Controller
{
    /**
     * @response array{data: array<int, array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}>}
     */
    public function index(): JsonResponse
    {
        $users = User::query()->with('roles')->orderBy('name')->get();

        return new JsonResponse([
            'data' => $users->map(fn (User $user) => $this->shape($user))->all(),
        ]);
    }

    /**
     * @response array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $password = $data['password'] ?? null;

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'department_id' => $data['department_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                /*
                 * With no password supplied the account gets an unguessable
                 * random one it will never be told. The person sets a real one
                 * through the reset flow — better than an administrator
                 * inventing a password and sending it over chat.
                 */
                'password' => Hash::make(is_string($password) ? $password : Str::random(64)),
            ]);

            $user->syncRoles([$data['role']]);

            return $user;
        });

        return new JsonResponse($this->shape($user->load('roles')), 201);
    }

    /**
     * @response array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}
     */
    public function show(User $user): JsonResponse
    {
        return new JsonResponse($this->shape($user->load('roles')));
    }

    /**
     * @response array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $user): void {
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'department_id', 'is_active'])));
            $user->save();

            if (isset($data['role'])) {
                // One role per user: syncRoles replaces rather than adds, so a
                // demotion actually removes the old authority.
                $user->syncRoles([$data['role']]);
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
                $this->revokeAccess($user);
            }
        });

        return new JsonResponse($this->shape($user->refresh()->load('roles')));
    }

    /**
     * Deactivate, never delete.
     *
     * @response array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}
     */
    public function deactivate(User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            /*
             * The row survives. Deleting it would break every historical
             * attribution that points at it — "assigned by", "note written by",
             * "closed by" — and turn an audit trail into a set of orphan ids.
             */
            $user->forceFill(['is_active' => false])->save();

            $this->revokeAccess($user);
        });

        return new JsonResponse($this->shape($user->refresh()->load('roles')));
    }

    /**
     * Ends every way this account could still be acting.
     *
     * Tokens AND sessions: the product runs Sanctum in cookie mode, so the
     * session rows are what actually authenticate a browser — deleting only
     * API tokens would leave an open tab fully signed in. EnsureActiveUser is
     * the backstop for anything this misses.
     */
    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }

    /**
     * @return array{id:int,name:string,email:string,role:string|null,department_id:int|null,is_active:bool}
     */
    private function shape(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => $user->roles->first()?->name,
            'department_id' => $user->department_id === null ? null : (int) $user->department_id,
            'is_active' => $user->isActive(),
        ];
    }
}
