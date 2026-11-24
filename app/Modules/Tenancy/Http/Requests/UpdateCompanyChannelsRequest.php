<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Data\ChannelSettingsData;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * "Canales de comunicación". Only the owner changes them (the words WhatsApp asks them to accept are theirs to accept).
 */
final class UpdateCompanyChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'mail_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'preferred' => ['required', 'in:mail,whatsapp'],
            'whatsapp_monthly_cap' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'consent_accepted' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(CompanyChannels $channels, CompanyPlans $plans, #[RouteParameter('tenant')] Tenant $tenant): array
    {
        return [function (Validator $validator) use ($channels, $plans, $tenant): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mail = $this->boolean('mail_enabled');
            $whatsApp = $this->boolean('whatsapp_enabled');

            if (! $mail && ! $whatsApp) {
                $validator->errors()->add('mail_enabled', 'Deja al menos un canal activo.');

                return;
            }

            if (($this->string('preferred')->toString() === 'mail' && ! $mail) || ($this->string('preferred')->toString() === 'whatsapp' && ! $whatsApp)) {
                $validator->errors()->add('preferred', 'El canal preferido tiene que estar activo.');
            }

            if ($whatsApp) {
                $this->checkWhatsApp($validator, $channels, $plans, $tenant);
            }
        }];
    }

    /**
     * Only a new activation is checked (it was off, or the owner accepted another version of the words): a WhatsApp that is already
     * on with the current words keeps being saved when the function is off in this deployment or the plan has ended, so the owner can
     * still change the preferred channel and the cap.
     */
    private function checkWhatsApp(Validator $validator, CompanyChannels $channels, CompanyPlans $plans, Tenant $tenant): void
    {
        $current = collect($channels->settings($tenant->id))->firstWhere('channel', 'whatsapp');

        if ($current !== null && $current->enabled && $current->consentVersion === config()->string('whatsapp.consent.version')) {
            return;
        }

        if (! config()->boolean('whatsapp.enabled')) {
            $validator->errors()->add('whatsapp_enabled', 'WhatsApp todavía no está disponible.');

            return;
        }

        if ($plans->current($tenant->id) === null) {
            $validator->errors()->add('whatsapp_enabled', 'Activa un plan para usar WhatsApp.');

            return;
        }

        if (! $this->boolean('consent_accepted')) {
            $validator->errors()->add('consent_accepted', 'Acepta el aviso para activar WhatsApp.');
        }
    }

    public function toData(): ChannelSettingsData
    {
        $cap = $this->input('whatsapp_monthly_cap');

        return new ChannelSettingsData(
            $this->boolean('mail_enabled'),
            $this->boolean('whatsapp_enabled'),
            $this->string('preferred')->toString(),
            is_numeric($cap) ? (int) $cap : null,
            $this->boolean('consent_accepted'),
        );
    }
}
