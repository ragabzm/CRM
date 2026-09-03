<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Modules\Security\Notifications\StaffPasswordReset;

#[Fillable(['name', 'email', 'password', 'preferred_locale', 'department_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
/*
 * TODO(Story 2.x): move this model into App\Modules\Security\Domain. It stays
 * in app/Models for now because Sanctum's guard configuration and Laravel's own
 * scaffolding reference it here, and relocating it is a change with its own
 * blast radius.
 */
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The staff member's language, defaulting to English.
     *
     * Null-coalesced rather than relying on the column default, so the model
     * still answers correctly against a database where the
     * add_preferred_locale migration has not been applied yet — the two
     * migrations in this story are independently additive and can land in
     * either order.
     */
    /**
     * Declared through `HasLocalePreference`, not just as a method.
     *
     * The interface is what makes Laravel wrap every notification send to this
     * person in THEIR language — the method alone was only being called by code
     * that remembered to. A supervisor working in English assigning a ticket to
     * a colleague working in Arabic must not send them an English email, and
     * that mistake is invisible to the person making it because their own
     * screen looks right.
     */
    public function preferredLocale(): string
    {
        $locale = $this->getAttributeValue('preferred_locale');

        return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
    }

    /**
     * Sends the branded staff reset mail rather than Laravel's default.
     *
     * The notification builds the link against the FRONTEND url: the reset form
     * is a Next.js route, and the API has no page to land on.
     */
    /**
     * Whether this account may still act.
     *
     * Null-coalesced to true so the model behaves correctly against a database
     * where the is_active migration has not been applied — the columns in this
     * story are additive and can land in either order.
     */
    public function isActive(): bool
    {
        return (bool) ($this->getAttributeValue('is_active') ?? true);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new StaffPasswordReset($token));
    }
}
