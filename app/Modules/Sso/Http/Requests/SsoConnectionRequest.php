<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Requests;

use App\Modules\Sso\Data\SsoConnectionData;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class SsoConnectionRequest extends FormRequest
{
    private const string DOMAIN = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(CurrentTenant $tenant): array
    {
        $hasSecret = SsoConnection::query()->where('tenant_id', $tenant->id())->exists();

        return [
            'issuer' => ['required', 'string', 'max:255', $this->issuer(...)],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => [$hasSecret ? 'nullable' : 'required', 'string', 'max:2000'],
            'domains' => ['required', 'array', 'min:1', 'max:5'],
            'domains.*' => ['required', 'string', 'regex:'.self::DOMAIN, 'distinct'],
        ];
    }

    public function toData(): SsoConnectionData
    {
        /** @var list<string> $domains */
        $domains = array_values(array_filter($this->array('domains'), is_string(...)));
        $secret = $this->string('client_secret')->toString();

        return new SsoConnectionData(
            issuer: $this->string('issuer')->toString(),
            clientId: $this->string('client_id')->toString(),
            clientSecret: $secret === '' ? null : $secret,
            domains: $domains,
        );
    }

    protected function prepareForValidation(): void
    {
        $issuer = $this->input('issuer');
        $domains = $this->input('domains');

        $this->merge([
            'issuer' => is_string($issuer) ? rtrim(trim($issuer), '/') : $issuer,
            'domains' => is_array($domains) ? array_map(fn (mixed $domain): mixed => is_string($domain) ? Str::lower(trim($domain)) : $domain, $domains) : $domains,
        ]);
    }

    /** @param Closure(string): void $fail */
    private function issuer(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $plain = is_array($parts) && ! isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment']);

        if (! $plain || ($parts['scheme'] ?? '') !== 'https' || ! str_contains($host, '.') || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            $fail('La dirección del proveedor debe empezar con https:// y ser un nombre de sitio público.');
        }
    }
}
