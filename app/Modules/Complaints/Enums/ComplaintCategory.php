<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/**
 * Humetría's classification of what is reported (criterio C-43): not a text of the DOF. Violence follows 4.12 (hostigamiento,
 * acoso, malos tratos); practices follow the elements of a favorable organizational environment (4.6, 7.3, 8.1 c, 8.2 a 2).
 */
enum ComplaintCategory: string
{
    case Harassment = 'harassment';
    case Bullying = 'bullying';
    case Mistreatment = 'mistreatment';
    case WorkloadOrHours = 'workload_or_hours';
    case UnclearResponsibilities = 'unclear_responsibilities';
    case LackOfTraining = 'lack_of_training';
    case LackOfRecognition = 'lack_of_recognition';
    case CommunicationOrParticipation = 'communication_or_participation';
    case Discrimination = 'discrimination';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Harassment => 'Hostigamiento',
            self::Bullying => 'Acoso',
            self::Mistreatment => 'Malos tratos',
            self::WorkloadOrHours => 'Cargas de trabajo o jornadas',
            self::UnclearResponsibilities => 'Responsabilidades poco claras',
            self::LackOfTraining => 'Falta de formación',
            self::LackOfRecognition => 'Falta de reconocimiento al desempeño',
            self::CommunicationOrParticipation => 'Falta de comunicación o de participación',
            self::Discrimination => 'Discriminación',
            self::Other => 'Otra práctica',
        };
    }

    public function type(): ComplaintType
    {
        return match ($this) {
            self::Harassment, self::Bullying, self::Mistreatment => ComplaintType::WorkplaceViolence,
            default => ComplaintType::UnfavorablePractice,
        };
    }

    /**
     * The categories a type admits; the traumatic event notice has none.
     *
     * @return list<self>
     */
    public static function of(ComplaintType $type): array
    {
        return array_values(array_filter(self::cases(), fn (self $category): bool => $category->type() === $type));
    }
}
