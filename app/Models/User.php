<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\UsernamePolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'disabled_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable, HasRoles;

    public static function superAdminRoleName(): string
    {
        return (string) config('yemen-motion-permissions.super_admin_role', 'super-admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::superAdminRoleName());
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isDisabled();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('disabled_at');
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->whereNotNull('disabled_at');
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => UsernamePolicy::normalize($value),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
