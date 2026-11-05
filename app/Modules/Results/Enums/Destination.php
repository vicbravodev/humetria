<?php

declare(strict_types=1);

namespace App\Modules\Results\Enums;

/**
 * Where a person who requires a clinical assessment was channeled (num. 5.5; board V4-N035-Canalizacion, decisión A7): a
 * closed list, so the record holds no clinical detail. «No quiso atenderse» also counts as channeled: care was offered.
 */
enum Destination: string
{
    case Imss = 'imss';
    case CompanyMedical = 'company_medical';
    case ExternalPsychological = 'external_psychological';
    case Other = 'other';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Imss => 'IMSS',
            self::CompanyMedical => 'Servicio médico de la empresa',
            self::ExternalPsychological => 'Apoyo psicológico externo',
            self::Other => 'Otra institución',
            self::Declined => 'No quiso atenderse',
        };
    }

    /** What the option says under its name. */
    public function hint(): string
    {
        return match ($this) {
            self::Imss => 'Su Unidad de Medicina Familiar',
            self::CompanyMedical => 'El médico o consultorio de tu empresa',
            self::ExternalPsychological => 'Programa de apoyo al empleado',
            self::Other => 'Pública o privada',
            self::Declined => 'Se le ofreció y lo rechazó',
        };
    }

    /** Where the person is proposed to go, as «tú» (the script) or «usted» (the notice). */
    public function proposal(bool $formal): string
    {
        return match ($this) {
            self::Imss => ($formal ? 'a su' : 'a tu').' Unidad de Medicina Familiar del IMSS',
            self::CompanyMedical => 'al servicio médico de la empresa',
            self::ExternalPsychological => 'al programa de apoyo psicológico',
            self::Other => $formal ? 'a la institución que prefiera' : 'a la institución que prefieras',
            self::Declined => 'a atención médica o psicológica',
        };
    }

    /** What HR can say in person, as «tú» to the person (the board's script). */
    public function script(): string
    {
        return "Por lo que contestaste en la encuesta, la empresa quiere ofrecerte apoyo. Es confidencial y no afecta tu trabajo. Te proponemos acudir {$this->proposal(false)}. ¿Te parece?";
    }

    /** What the person reads in their portal, as «usted». */
    public function notice(): string
    {
        return "Por lo que contestó en la encuesta, la empresa quiere ofrecerle apoyo. Es confidencial y no afecta su trabajo. Le proponemos acudir {$this->proposal(true)}. Si tiene dudas, puede hablar con Recursos Humanos.";
    }
}
