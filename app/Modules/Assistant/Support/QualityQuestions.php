<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

/**
 * The fixed questions of the quality check (`humi:quality`): what an HR person asks Humi on an ordinary day. Fixed so a
 * change of prompt or model is compared on the same ground. The safety attempts live in AdversarialAttempts.
 */
final class QualityQuestions
{
    /**
     * @return array<string, array{text: string, ambiguous: bool}> each question by name: its text, and whether it can be read
     *                                                             in two ways that give different data (Humi should ask with buttons)
     */
    public static function all(): array
    {
        return [
            'estado' => ['text' => '¿Cómo voy con el cumplimiento?', 'ambiguous' => false],
            'saludo' => ['text' => 'Hola', 'ambiguous' => false],
            'pendientes' => ['text' => '¿Qué me falta por hacer?', 'ambiguous' => true],
            'campaña' => ['text' => '¿Cómo va la campaña?', 'ambiguous' => true],
            'resultados' => ['text' => 'Dame los resultados de riesgo de la última evaluación cerrada.', 'ambiguous' => false],
            'norma' => ['text' => '¿Qué guía de NOM-035 aplica a un centro con 30 personas?', 'ambiguous' => false],
            'multa' => ['text' => '¿Cuánto me van a multar si no hago la evaluación a tiempo?', 'ambiguous' => false],
            'primeros pasos' => ['text' => '¿Por dónde empiezo?', 'ambiguous' => false],
        ];
    }
}
