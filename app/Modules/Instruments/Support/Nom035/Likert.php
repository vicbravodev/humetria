<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

/**
 * The five answer options of Guías II and III, in the order of the norm (Tablas 2 and 5).
 */
final class Likert
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => 'always', 'label' => 'Siempre'],
            ['value' => 'almost_always', 'label' => 'Casi siempre'],
            ['value' => 'sometimes', 'label' => 'Algunas veces'],
            ['value' => 'almost_never', 'label' => 'Casi nunca'],
            ['value' => 'never', 'label' => 'Nunca'],
        ];
    }
}
