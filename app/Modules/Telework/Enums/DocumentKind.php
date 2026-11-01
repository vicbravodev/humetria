<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/**
 * The documents NOM-037 asks the company to keep (criterios C-19, C-21, C-23), each a PDF the company uploads. Only the
 * policy (5.2 a–l) and the implementation process (5.6 a–d) have items the company marks as covered.
 */
enum DocumentKind: string
{
    case Policy = 'policy';
    case RiskInformation = 'risk_information';
    case ImplementationProcess = 'implementation_process';
    case MaintenanceProgram = 'maintenance_program';
    case Reversibility = 'reversibility';
    case FamilyViolence = 'family_violence';
    case CommitteeParticipation = 'committee_participation';
    case InspectionReport = 'inspection_report';

    public function label(): string
    {
        return match ($this) {
            self::Policy => 'Política de Teletrabajo',
            self::RiskInformation => 'Información sobre riesgos del Teletrabajo',
            self::ImplementationProcess => 'Proceso de implementación del Teletrabajo',
            self::MaintenanceProgram => 'Programa de mantenimiento de equipos TIC',
            self::Reversibility => 'Mecanismo de reversibilidad',
            self::FamilyViolence => 'Mecanismo de atención de violencia familiar',
            self::CommitteeParticipation => 'Apoyo para participar en las comisiones',
            self::InspectionReport => 'Dictamen de inspección',
        };
    }

    /** The numeral of the norm the document answers to, when it has one. */
    public function numeral(): ?string
    {
        return match ($this) {
            self::Policy => '5.2',
            self::RiskInformation => '5.3',
            self::ImplementationProcess => '5.6',
            self::MaintenanceProgram => '5.8',
            self::Reversibility => '5.10',
            self::FamilyViolence => '5.13',
            self::CommitteeParticipation => '5.14',
            self::InspectionReport => null,
        };
    }

    /**
     * The items the company can mark as covered, by letter, with a short label faithful to the norm's text.
     *
     * @return array<string, string>
     */
    public function items(): array
    {
        return match ($this) {
            self::Policy => [
                'a' => 'Cultura de prevención de riesgos de trabajo',
                'b' => 'Comunicación para evitar el aislamiento social',
                'c' => 'Comunicación y difusión a distancia de los procedimientos',
                'd' => 'Mecanismos y reglas de contacto que respetan la privacidad',
                'e' => 'Horario, pausas y derecho a la desconexión',
                'f' => 'Derechos no inferiores a los del trabajo presencial',
                'g' => 'Perspectiva de género y conciliación de la vida personal',
                'h' => 'Horario de lactancia',
                'i' => 'Promoción y vigilancia de la salud',
                'j' => 'Aviso de cambios de domicilio del lugar de trabajo',
                'k' => 'Responsabilidades y obligaciones de patrones y personas trabajadoras',
                'l' => 'Mecanismos de reversibilidad del Teletrabajo',
            ],
            self::ImplementationProcess => [
                'a' => 'Comunicación entre la persona trabajadora y el centro de trabajo',
                'b' => 'Momentos y causas en que asistirá al centro de trabajo',
                'c' => 'Forma de supervisar el Teletrabajo',
                'd' => 'Mantenimiento del equipo y las herramientas',
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public function coverableItems(): array
    {
        return array_keys($this->items());
    }

    /** Whether people have to confirm they read it: the policy and the risk information (criterio C-20). */
    public function needsAcknowledgement(): bool
    {
        return $this === self::Policy || $this === self::RiskInformation;
    }
}
