<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Support\Writing\WrittenTemplate;

/** Which NOM-035 texts are written with questions (decisión A3), and with which template; the engine is App\Support\Writing. */
final readonly class Templates
{
    public function __construct(private Nom035Policy $guide) {}

    public function template(DocumentKind $kind): WrittenTemplate
    {
        return match ($kind) {
            DocumentKind::Policy => new PolicyTemplate($this->guide),
            DocumentKind::ViolenceMeasures => new ViolenceTemplate,
            DocumentKind::PreventionMeasures => new PreventionTemplate,
            DocumentKind::ComplaintMechanism => new ComplaintTemplate,
            DocumentKind::HealthEffects => new HealthTemplate,
        };
    }
}
