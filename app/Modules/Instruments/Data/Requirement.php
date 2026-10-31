<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A condition of NOM-037 (numeral 5.1.1 or chapter 7) that the inspection checks and a checklist question covers.
 */
enum Requirement: string
{
    case Connectivity = '5.1.1a';
    case Electrical = '5.1.1b';
    case CleanTidy = '7.1a';
    case Lighting = '7.1b';
    case TemperatureVentilation = '7.1c';
    case Noise = '7.1d';
    case WorkSurface = '7.2a';
    case Chair = '7.2b';
    case ErgonomicAids = '7.2c';
    case Privacy = '7.3a';
    case WorkLifeBalance = '7.3b';
    case FamilyViolenceChannel = '7.3c';
    case Breaks = '7.3d';
    case Disconnection = '7.3e';

    public function label(): string
    {
        return match ($this) {
            self::Connectivity => 'Conectividad para las TIC',
            self::Electrical => 'Instalaciones eléctricas en buen estado',
            self::CleanTidy => 'Área limpia y ordenada',
            self::Lighting => 'Iluminación sin deslumbramiento ni fatiga visual',
            self::TemperatureVentilation => 'Temperatura y ventilación agradables',
            self::Noise => 'Ruido que no impide la concentración',
            self::WorkSurface => 'Plano de trabajo para apoyar los brazos, sin estorbos bajo la mesa',
            self::Chair => 'Silla apropiada',
            self::ErgonomicAids => 'Aditamentos ergonómicos o posturales',
            self::Privacy => 'Espacio con privacidad',
            self::WorkLifeBalance => 'Conciliación entre trabajo y vida familiar',
            self::FamilyViolenceChannel => 'Mecanismo para avisar de violencia familiar',
            self::Breaks => 'Pausas y tiempos de descanso',
            self::Disconnection => 'Derecho a la desconexión',
        };
    }
}
