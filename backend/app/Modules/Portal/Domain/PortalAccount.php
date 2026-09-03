<?php

declare(strict_types=1);

namespace App\Modules\Portal\Domain;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Modules\Portal\Notifications\PortalPasswordReset;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;

/**
 * A customer's portal login.
 *
 * Deliberately a separate table and a separate model from App\Models\User, not
 * a flag on one shared table. Staff and customers are different populations
 * with different lifecycles, and the isolation is structural: the `portal` guard
 * queries `portal_accounts` and literally cannot see a staff row, so a staff
 * credential is not "rejected" here — it is invisible.
 *
 * A discriminator column would make "is this person staff?" a value that can be
 * set wrong. Two tables make the question unanswerable in the wrong direction.
 *
 * Nothing in the Security or Platform modules imports this class; the two
 * identity spaces meet only in config/auth.php, by class-string.
 */
final class PortalAccount extends Authenticatable implements HasLocalePreference
{
    use Notifiable;

    protected $table = 'portal_accounts';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The language this person chose for themselves.
     *
     * Declared through `HasLocalePreference`, not just as a method: a password
     * reset email is composed on the server, in the recipient's language, and
     * the interface is what makes Laravel do that without every call site
     * remembering to. A customer who registered in Arabic and gets an English
     * reset link has been told, in a language they may not read, how to get
     * back into their account.
     */
    public function preferredLocale(): string
    {
        $locale = $this->getAttributeValue('preferred_locale');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }

    /**
     * Sends the PORTAL reset mail, not Laravel's default.
     *
     * The link points at the portal's own reset route, which is a different
     * page in a different shell from the staff one. Laravel's default would
     * send a customer to the staff sign-in form.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PortalPasswordReset($token, $this->email));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
