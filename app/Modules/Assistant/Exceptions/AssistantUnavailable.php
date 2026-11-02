<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Exceptions;

use RuntimeException;

/**
 * Humi will not answer this message, before any call to the provider: it is off for the company, the company used its
 * budget of the month, or the person (or the company) wrote too much for now. The message is what the panel says.
 */
final class AssistantUnavailable extends RuntimeException
{
    public const string DISABLED = 'disabled';

    public const string BUDGET = 'budget';

    public const string RATE = 'rate';

    private function __construct(public readonly string $reason, string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED, 'Humi no está disponible para tu empresa. Pídele a tu administrador que la active.');
    }

    public static function budgetUsed(): self
    {
        return new self(self::BUDGET, 'Tu empresa agotó el uso de Humi de este mes. Se renueva el primer día del mes.');
    }

    public static function tooMany(int $retryAfter): self
    {
        return new self(self::RATE, 'Alcanzaste el límite de mensajes por ahora. Puedes seguir en unos minutos.', $retryAfter);
    }
}
