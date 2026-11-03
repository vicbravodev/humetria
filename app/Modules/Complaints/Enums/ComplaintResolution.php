<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/** How a handler closed a case. It is the handler's classification, not a finding of the platform. */
enum ComplaintResolution: string
{
    case MeasuresTaken = 'measures_taken';
    case NoActionNeeded = 'no_action_needed';
    case ReferredExternally = 'referred_externally';
    case InsufficientInformation = 'insufficient_information';
    case Withdrawn = 'withdrawn';
    case DiscardedSpam = 'discarded_spam';

    public function label(): string
    {
        return match ($this) {
            self::MeasuresTaken => 'Se tomaron medidas',
            self::NoActionNeeded => 'No requirió medidas',
            self::ReferredExternally => 'Se canalizó a otra instancia',
            self::InsufficientInformation => 'Información insuficiente',
            self::Withdrawn => 'Retirada por la persona',
            self::DiscardedSpam => 'Descartada (no era una queja)',
        };
    }

    /** What the person is told in general words. */
    public function reporterLabel(): string
    {
        return match ($this) {
            self::MeasuresTaken => 'La empresa tomó medidas.',
            self::NoActionNeeded => 'La empresa revisó el caso y no encontró que requiriera medidas.',
            self::ReferredExternally => 'La empresa canalizó el caso a otra instancia.',
            self::InsufficientInformation => 'No hubo información suficiente para continuar.',
            self::Withdrawn => 'Usted retiró el caso.',
            self::DiscardedSpam => 'La empresa cerró el caso sin darle trámite.',
        };
    }
}
