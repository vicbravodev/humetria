<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\TransferContext;
use App\Modules\Billing\Data\TransferData;
use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Exceptions\InvalidTransfer;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Billing\Support\Eligibility;
use App\Modules\Billing\Support\QuoteView;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Billing\Support\SubscriptionQuoter;
use App\Modules\Billing\Support\TransferGuard;
use Carbon\CarbonImmutable;

final readonly class PreviewTransfer
{
    public function __construct(private Eligibility $eligibility, private SubscriptionQuoter $quoter) {}

    /**
     * What moving the company would do, with nothing written: what it pays now next to what it would pay with the new payer,
     * and, for each consultant's account, whether it loses or gains the Integral discount at the renewal of that day.
     *
     * @return array{from: array{id: string, name: string}, to: array{id: string, name: string}|null, effective_on: string, current: array<string, mixed>|null, quote: array<string, mixed>|null, profile: array{before: string, after: string}|null, effects: list<array<string, mixed>>, access: string}
     *
     * @throws InvalidTransfer
     */
    public function handle(string $tenantId, TransferData $data): array
    {
        $context = TransferGuard::check($tenantId, $data);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $subscription = $context->subscription;
        $after = $subscription?->replicate();

        if ($after !== null && $context->profileAfter !== null) {
            $after->profile = $context->profileAfter;
        }

        $fromBefore = $this->eligibility->count($context->from);
        $toBefore = $context->to === null ? 0 : $this->eligibility->count($context->to);
        $toAfter = $context->to === null ? 0 : $this->eligibility->count($context->to, $after);

        return [
            'from' => $this->account($context->from),
            'to' => $context->to === null ? null : $this->account($context->to),
            'effective_on' => ShortDate::format($data->effectiveOn),
            'current' => $subscription === null ? null : QuoteView::from($this->quoter->quote($subscription, $fromBefore, $today)),
            'quote' => $after === null || $context->to === null ? null : QuoteView::from($this->quoter->quote($after, $toAfter, $data->effectiveOn)),
            'profile' => $subscription === null || $context->profileAfter === null || $context->profileAfter === $subscription->profile ? null : ['before' => $subscription->profile->label(), 'after' => $context->profileAfter->label()],
            'effects' => $this->effects($context, $data, $fromBefore, $this->eligibility->count($context->from, without: $tenantId), $toBefore, $toAfter),
            'access' => 'Trasladar una empresa no da ni quita acceso a nadie: las membresías no se tocan.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function effects(TransferContext $context, TransferData $data, int $fromBefore, int $fromAfter, int $toBefore, int $toAfter): array
    {
        $effects = [];

        if ($context->from->kind === AccountKind::Consultant) {
            $effects[] = $this->effect('from', $context->from, $fromBefore, $fromAfter, $data->effectiveOn);
        }

        if ($context->to !== null && $context->to->kind === AccountKind::Consultant) {
            $effects[] = $this->effect('to', $context->to, $toBefore, $toAfter, $data->effectiveOn);
        }

        return $effects;
    }

    /**
     * @return array<string, mixed>
     */
    private function effect(string $role, BillingAccount $account, int $before, int $after, CarbonImmutable $on): array
    {
        $minimum = Catalog::current()->integralMinimumCompanies();
        $day = ShortDate::format($on);
        $effect = match (true) {
            $before >= $minimum && $after < $minimum => 'loses',
            $before < $minimum && $after >= $minimum => 'gains',
            default => 'same',
        };

        return [
            'role' => $role,
            'account' => $account->legal_name,
            'before' => $before,
            'after' => $after,
            'effect' => $effect,
            'text' => match ($effect) {
                'loses' => "{$account->legal_name} pierde el descuento de canal Integral desde la renovación del {$day}.",
                'gains' => "{$account->legal_name} gana el descuento de canal Integral desde la renovación del {$day}.",
                default => "{$account->legal_name} no cambia su descuento de canal Integral (de {$before} a {$after} empresas con Integral pagado).",
            },
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    private function account(BillingAccount $account): array
    {
        return ['id' => $account->id, 'name' => $account->legal_name];
    }
}
