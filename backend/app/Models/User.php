<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Modules\Security\Notifications\StaffPasswordReset;

#[Fillable(['name', 'email', 'password', 'preferred_locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new StaffPasswordReset($token));
    }
}
