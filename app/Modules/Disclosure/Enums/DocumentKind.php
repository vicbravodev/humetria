<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

/**
 * What a text of the company is for: the policy (5.1, 5.7 a) or one of the blocks of 5.7 that the company writes itself
 * (b, c, d and f).
 */
enum DocumentKind: string
{
    case Policy = 'policy';
    case ViolenceMeasures = 'violence_measures';
    case PreventionMeasures = 'prevention_measures';
    case ComplaintMechanism = 'complaint_mechanism';
    case HealthEffects = 'health_effects';

    public function label(): string
    {
        return match ($this) {
            self::Policy => 'Política de prevención',
            self::ViolenceMeasures => 'Medidas contra la violencia laboral',
            self::PreventionMeasures => 'Medidas y acciones de prevención y control',
            self::ComplaintMechanism => 'Mecanismos para presentar quejas',
            self::HealthEffects => 'Posibles alteraciones a la salud',
        };
    }

    /** The inciso of numeral 5.7 the text is about: the policy is a, the blocks the company writes are b, c, d and f. */
    public function inciso(): string
    {
        return match ($this) {
            self::Policy => 'a',
            self::ViolenceMeasures => 'b',
            self::PreventionMeasures => 'c',
            self::ComplaintMechanism => 'd',
            self::HealthEffects => 'f',
        };
    }

    /** What the company is asked to say, in the lower case of a sentence («las medidas contra la violencia laboral»). */
    public function topic(): string
    {
        return match ($this) {
            self::Policy => 'la política de prevención',
            self::ViolenceMeasures => 'las medidas contra la violencia laboral',
            self::PreventionMeasures => 'las medidas y acciones de prevención y control',
            self::ComplaintMechanism => 'los mecanismos para presentar quejas',
            self::HealthEffects => 'las posibles alteraciones a la salud',
        };
    }

    /** Whether it is one of the blocks of 5.7 the company writes by hand (b, c, d and f), as against the policy. */
    public function isBlock(): bool
    {
        return $this !== self::Policy;
    }

    /**
     * How to write it: what the norm asks and what to avoid. A text the platform proposes says so, here, and not in the text.
     */
    public function help(): string
    {
        return match ($this) {
            self::Policy => '',
            self::ViolenceMeasures => 'Qué hace el centro para atender las prácticas contrarias al entorno favorable y los actos de violencia laboral (numeral 5.7 b): quién atiende, qué pasa después de una queja, qué se sanciona.',
            self::PreventionMeasures => 'Las acciones del centro para prevenir y, en su caso, controlar los factores de riesgo psicosocial (numeral 5.7 c): capacitación, ajustes de cargas de trabajo, campañas.',
            self::ComplaintMechanism => 'Por dónde se presenta una queja o una denuncia (numeral 5.7 d): un buzón, un correo, una persona o la Comisión de seguridad e higiene.',
            self::HealthEffects => 'Borrador de Humetría, basado en la definición del numeral 4.7: el DOF dice qué informar, pero no el texto. Revíselo con una persona con criterio médico o legal antes de publicarlo.',
        };
    }
}
