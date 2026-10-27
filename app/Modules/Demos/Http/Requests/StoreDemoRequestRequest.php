<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Workforce;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreDemoRequestRequest extends FormRequest
{
    /**
     * The honeypot: a field people never see. Only a bot fills it in.
     */
    public const string HONEYPOT = 'website';

    /**
     * @return array<string, list<Enum|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            // 10 digits, optionally after 52 or +52, once spaces, dashes, dots and parentheses are gone.
            'whatsapp' => ['required', 'regex:/^(\+?52)?[0-9]{10}$/'],
            'company' => ['required', 'string', 'max:160'],
            'profile' => ['required', Rule::enum(DemoProfile::class)],
            'workforce' => ['required', Rule::enum(Workforce::class)],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function isFromBot(): bool
    {
        return $this->filled(self::HONEYPOT);
    }

    public function toData(): DemoRequestData
    {
        return new DemoRequestData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            whatsapp: '+52'.$this->string('whatsapp')->substr(-10)->toString(),
            company: $this->string('company')->toString(),
            profile: DemoProfile::from($this->string('profile')->toString()),
            workforce: Workforce::from($this->string('workforce')->toString()),
            message: $this->filled('message') ? $this->string('message')->toString() : null,
        );
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = $this->input('whatsapp');

        if (is_string($whatsapp)) {
            $this->merge(['whatsapp' => preg_replace('/[\s().-]+/', '', $whatsapp)]);
        }
    }
}
