<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\SearchesText;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\RequestOrigin;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SearchesText, TwoFactorAuthenticatable;

    /** Platform role (Humetría staff): passes every ability, see AppServiceProvider. */
    public const string SUPERADMIN = 'superadmin';

    /** Platform role (Humetría staff): sees the console and works as an owner, and changes nothing about a company. */
    public const string SUPPORT = 'support';

    /** @var list<string>|null */
    private ?array $platformRoles = null;

    /**
     * Whether the user holds the platform's superadmin role. That Spatie role belongs to no company, so it is read
     * past the current team: inside a company, hasRole() only sees that company's roles. Read once per instance.
     */
    public function isSuperadmin(): bool
    {
        return in_array(self::SUPERADMIN, $this->platformRoles(), true);
    }

    /** Whether the user holds the platform's support role, read with the superadmin's in the same query. */
    public function isSupport(): bool
    {
        return in_array(self::SUPPORT, $this->platformRoles(), true);
    }

    /** Humetría's own people: the console and impersonation are for them, each with what their role allows. */
    public function isStaff(): bool
    {
        return $this->platformRoles() !== [];
    }

    /**
     * The platform roles the user holds, in one query for both.
     *
     * @return list<string>
     */
    private function platformRoles(): array
    {
        if ($this->platformRoles !== null) {
            return $this->platformRoles;
        }

        $held = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [self::SUPERADMIN, self::SUPPORT])
            ->whereNull('model_has_roles.tenant_id')
            ->where('model_has_roles.model_type', $this->getMorphClass())
            ->where('model_has_roles.model_id', $this->id)
            ->select('roles.name')
            ->get();

        $this->platformRoles = [];

        foreach ($held as $role) {
            if (is_string($role->name)) {
                $this->platformRoles[] = $role->name;
            }
        }

        return $this->platformRoles;
    }

    /**
     * Fortify's emails go through the queue.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        // Fortify asks for it while serving the request: where it came from is read now, before the queue.
        $request = request();
        $origin = resolve(RequestOrigin::class)->describe($request->userAgent() ?? '', $request->ip() ?? '', now());

        $this->notify(new ResetPasswordNotification($token, $origin));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
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
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
