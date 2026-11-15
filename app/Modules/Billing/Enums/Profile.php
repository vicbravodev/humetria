<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** How a subscription is priced: the direct table, a consultant's channel rate (compliance bands or Integral with its discount), or a price agreed by hand. */
enum Profile: string
{
    case Direct = 'direct';
    case ChannelCompliance = 'channel_compliance';
    case ChannelIntegral = 'channel_integral';
    case Negotiated = 'negotiated';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Directo',
            self::ChannelCompliance => 'Canal Cumplimiento',
            self::ChannelIntegral => 'Canal Integral',
            self::Negotiated => 'Precio negociado',
        };
    }

    /** Whether the profile is a consultant's rate: only an account of a consultant pays it. */
    public function isChannel(): bool
    {
        return match ($this) {
            self::ChannelCompliance, self::ChannelIntegral => true,
            self::Direct, self::Negotiated => false,
        };
    }

    /** The only plan a channel profile can be combined with; null for the others, which take any. */
    public function requiredPlan(): ?Plan
    {
        return match ($this) {
            self::ChannelCompliance => Plan::Cumplimiento,
            self::ChannelIntegral => Plan::Integral,
            self::Direct, self::Negotiated => null,
        };
    }
}
