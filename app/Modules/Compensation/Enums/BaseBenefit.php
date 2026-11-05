<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** The benefits every account starts with (spec, decision 6). The value is the stable key `benefits.base_key` holds. */
enum BaseBenefit: string
{
    case ChristmasBonus = 'christmas_bonus';
    case Vacation = 'vacation';
    case VacationPremium = 'vacation_premium';
    case SavingsFund = 'savings_fund';
    case FoodVouchers = 'food_vouchers';
    case SeniorityPremium = 'seniority_premium';
    case SundayPremium = 'sunday_premium';
    case ProductivityBonus = 'productivity_bonus';
    case AttendanceBonus = 'attendance_bonus';
    case MajorMedical = 'major_medical';
    case RetirementFund = 'retirement_fund';

    /** The name the account sees. */
    public function label(): string
    {
        return match ($this) {
            self::ChristmasBonus => 'Aguinaldo',
            self::Vacation => 'Vacaciones',
            self::VacationPremium => 'Prima vacacional',
            self::SavingsFund => 'Fondo de ahorro',
            self::FoodVouchers => 'Vales de despensa',
            self::SeniorityPremium => 'Prima de antigüedad',
            self::SundayPremium => 'Prima dominical',
            self::ProductivityBonus => 'Bono de productividad',
            self::AttendanceBonus => 'Bono de asistencia y puntualidad',
            self::MajorMedical => 'Gastos médicos mayores',
            self::RetirementFund => 'Fondo de jubilación',
        };
    }
}
