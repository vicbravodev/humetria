<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreDemoRequestRequest extends FormRequest
{
    /**
     * The honeypot: a field people never see. Only a bot fills it in.
     */
    public const string HONEYPOT = 'form_extra';

    /**
     * Valid UTF-8 without control characters: Postgres rejects invalid UTF-8 (and the
     * exception would carry the submitted values) and pdo_pgsql silently cuts at a NUL.
     */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    /** Like SINGLE_LINE, but a message may keep tabs and line breaks. */
    private const string MULTI_LINE = 'regex:/\A[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*\z/u';

    /**
     * @return array<string, list<Enum|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', self::SINGLE_LINE, 'max:120'],
            'email' => ['required', 'email:rfc', self::SINGLE_LINE, 'max:190'],
            // 10 digits, optionally after 52 or +52, once spaces, dashes, dots and parentheses are gone.
            'whatsapp' => ['required', 'regex:/^(\+?52)?[0-9]{10}$/'],
            'company' => ['required', self::SINGLE_LINE, 'max:160'],
            'profile' => ['required', Rule::enum(DemoProfile::class)],
            'workforce' => ['required', Rule::enum(Workforce::class)],
            'tools' => ['required', 'array', 'min:1', 'max:3'],
            'tools.*' => ['required', 'distinct', Rule::enum(Tool::class)],
            'message' => ['nullable', self::MULTI_LINE, 'max:2000'],
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
            tools: array_values($this->enums('tools', Tool::class)),
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
