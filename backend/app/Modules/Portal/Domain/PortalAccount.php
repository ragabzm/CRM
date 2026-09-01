<?php

declare(strict_types=1);

namespace App\Modules\Portal\Domain;

use Illuminate\Foundation\Auth\User as Authenticatable;
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
final class PortalAccount extends Authenticatable
{
    use Notifiable;

    protected $table = 'portal_accounts';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

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
