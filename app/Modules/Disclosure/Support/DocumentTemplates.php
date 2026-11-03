<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;

/**
 * The sections each kind of text starts from and may keep: the server owns the keys, headings and list styles, HR owns the
 * words. The policy starts from the example of Guía IV; the blocks of 5.7 start from a base text of their own.
 */
final readonly class DocumentTemplates
{
    public function __construct(private Nom035Policy $policy) {}

    /**
     * @return list<PolicySection>
     */
    public function sections(DocumentKind $kind): array
    {
        return match ($kind) {
            DocumentKind::Policy => $this->policy->template()->sections,
            default => [new PolicySection('body', $kind->label(), $this->base($kind), removable: false, required: true)],
        };
    }

    /**
     * The sections as a draft stores them.
     *
     * @return list<array{key: string, heading: string, text: string, items: list<string>, list_style: string|null}>
     */
    public function content(DocumentKind $kind): array
    {
        return array_map(fn (PolicySection $section): array => [
            'key' => $section->key,
            'heading' => $section->heading,
            'text' => $section->text,
            'items' => $section->items,
            'list_style' => $section->listStyle,
        ], $this->sections($kind));
    }

    /**
     * The text a block starts from. Only the health effects have one: the definition of the numeral 4.7 (literal) and what the
     * people can do, written as a draft for the company to review.
     */
    private function base(DocumentKind $kind): string
    {
        return $kind === DocumentKind::HealthEffects
            ? "Los factores de riesgo psicosocial son aquellos que pueden provocar trastornos de ansiedad, no orgánicos del ciclo sueño-vigilia y de estrés grave y de adaptación, derivado de la naturaleza de las funciones del puesto de trabajo, el tipo de jornada de trabajo y la exposición a acontecimientos traumáticos severos o a actos de violencia laboral al trabajador, por el trabajo desarrollado.\n\nSi usted nota alguno de estos síntomas, puede acudir a su institución de seguridad social o al médico del centro de trabajo, o hablar con Recursos Humanos."
            : '';
    }
}
