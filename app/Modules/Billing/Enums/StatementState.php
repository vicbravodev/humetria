<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

/** How a statement stands for the person who collects: a draft, or an issued one by how much of it has been paid. Derived, never stored. */
enum StatementState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Credit = 'credit';

    /** `$balanceCents` is the statement's one balance (Statement::selectBalance): nothing left, after payments and credits, is paid. */
    public static function of(StatementStatus $status, int $totalCents, int $paidCents, int $balanceCents): self
    {
        return match (true) {
            $status === StatementStatus::Draft => self::Draft,
            $totalCents < 0 => self::Credit,
            $balanceCents <= 0 => self::Paid,
            $paidCents > 0 => self::PartiallyPaid,
            default => self::Open,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Open => 'Emitido',
            self::PartiallyPaid => 'Pago parcial',
            self::Paid => 'Pagado',
            self::Credit => 'Crédito',
        };
    }
}
