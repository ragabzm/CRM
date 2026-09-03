<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Contracts\CustomerDirectory;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Portal\Http\Requests\RegisterPortalAccountRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registering, signing in and signing out — on the PORTAL guard.
 *
 * A separate guard from staff, not a role on the staff one. Staff and customers
 * are different populations in different tables, and a single guard would make
 * "which kind of person is this?" a runtime question every endpoint had to get
 * right — with the failure mode being a customer reaching an agent's screen.
 *
 * Cookie-mode Sanctum throughout: no token is requested, returned or stored,
 * so no credential is ever handled by client JavaScript.
 */
final class PortalAuthController extends Controller
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    /**
     * @response array<string, mixed>
     */
    public function register(RegisterPortalAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = (string) $data['email'];

        $account = DB::transaction(function () use ($data, $email): PortalAccount {
            /*
             * Linked to the customer record the business may already have.
             *
             * Somebody who has emailed support before is already in the
             * database — often auto-created from that email. Registering
             * without linking would give them a portal that shows none of
             * their own history, which reads as the product having lost it.
             */
            $customerId = $this->customers->findIdByEmail($email)
                ?? $this->customers->createFromAddress($email, (string) $data['name'], 'portal_registration');

            $account = new PortalAccount;

            $account->forceFill([
                'name' => (string) $data['name'],
                'email' => $email,
                'password' => (string) $data['password'],
                // English is the fallback, not a preference anybody expressed.
                'preferred_locale' => (string) ($data['preferred_locale'] ?? 'en') ?: 'en',
                'customer_id' => $customerId,
            ])->save();

            return $account;
        });

        // Signed in immediately: making somebody register and then sign in is
        // asking them to prove twice what they just proved once.
        Auth::guard('portal')->login($account);
        $request->session()->regenerate();

        return new JsonResponse($this->shape($account), 201);
    }

    /**
     * @response array<string, mixed>
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower(trim((string) $credentials['email']));

        if (! Auth::guard('portal')->attempt($credentials, (bool) $request->boolean('remember'))) {
            /*
             * One message for a wrong address and a wrong password. Telling
             * them apart turns this endpoint into a way to discover which
             * addresses have accounts.
             */
            throw ProblemException::make(
                'portal.invalid_credentials',
                'Those details did not match',
                401,
                'Check the email address and password and try again.',
            );
        }

        // Against session fixation: the id somebody may have planted before
        // sign-in must not become the id of the signed-in session.
        $request->session()->regenerate();

        return new JsonResponse($this->shape(Auth::guard('portal')->user()));
    }

    /**
     * @response array{status: string}
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('portal')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse(['status' => 'signed_out']);
    }

    /**
     * @response array<string, mixed>
     */
    public function me(Request $request): JsonResponse
    {
        return new JsonResponse($this->shape($request->user('portal')));
    }

    /**
     * What the portal knows about the person using it.
     *
     * NO roles, NO capabilities, NO department. A portal account holds none of
     * those, and publishing empty ones would invite a frontend to branch on
     * them — which is the first step toward a customer being shown a staff
     * surface because an array happened to be empty rather than absent.
     *
     * @return array<string, mixed>
     */
    private function shape(mixed $account): array
    {
        if (! $account instanceof PortalAccount) {
            throw ProblemException::make(
                'platform.unauthorized',
                'Authentication required.',
                401,
                'Sign in first.',
            );
        }

        return [
            'id' => (int) $account->getKey(),
            'name' => $account->name,
            'email' => $account->email,
            'preferred_locale' => $account->preferredLocale(),
            'customer_id' => $account->getAttribute('customer_id'),
        ];
    }
}
