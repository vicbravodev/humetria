<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/**
 * Against whom a complaint is, by role and never by name. It decides who may not receive it: nobody handles a case against
 * their own role (criterio C-45).
 */
enum RespondentKind: string
{
    case DirectSupervisor = 'direct_supervisor';
    case Coworker = 'coworker';
    case Hr = 'hr';
    case Management = 'management';
    case ThirdParty = 'third_party';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::DirectSupervisor => 'Mi jefa o jefe inmediato',
            self::Coworker => 'Una compañera o compañero',
            self::Hr => 'Recursos humanos',
            self::Management => 'La dirección o el dueño',
            self::ThirdParty => 'Una persona ajena a la empresa (cliente, proveedor)',
            self::Unknown => 'No sé o prefiero no decirlo',
        };
    }

    /** The label for whoever handles the case, short. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::DirectSupervisor => 'Jefa o jefe inmediato',
            self::Coworker => 'Compañera o compañero',
            self::Hr => 'Recursos humanos',
            self::Management => 'La dirección o el dueño',
            self::ThirdParty => 'Persona ajena a la empresa',
            self::Unknown => 'No sabe o prefiere no decirlo',
        };
    }

    /** The company role (by name) that must not handle a complaint against this kind; null when none is left out. */
    public function excludedRole(): ?string
    {
        return match ($this) {
            self::Hr => 'hr',
            self::Management => 'owner',
            default => null,
        };
    }
}
