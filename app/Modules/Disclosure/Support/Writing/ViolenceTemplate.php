<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The measures against workplace violence (num. 5.7 b) written with four questions (board V4-Difusion-Contenidos): what is
 * violence, what happens when someone reports it, what the company may do and how it protects who reports. The questions,
 * options and what the board marks at first are the board's; the people read it in «usted».
 *
 * TODO-LEGAL: a lawyer validates every text here before production, and that the measures match the company's reglamento
 * interior de trabajo.
 */
final readonly class ViolenceTemplate implements WrittenTemplate
{
    private const array CONSIDERED = ['shouting' => 'Gritos, insultos o amenazas', 'humiliation' => 'Humillaciones o burlas', 'harassment' => 'Acoso u hostigamiento', 'exclusion' => 'Dejar a alguien fuera del trabajo a propósito', 'damage' => 'Daños a sus cosas o a su trabajo'];

    private const array REPORT = ['acknowledge' => 'Le confirmamos en 3 días que la recibimos y le respondemos en 30 días qué hicimos', 'committee' => 'La revisa la Comisión de seguridad e higiene y le responde en 30 días'];

    private const array MEASURES = ['warning' => 'Llamada de atención por escrito', 'transfer' => 'Cambio de turno o de área de quien la cometió', 'suspension' => 'Suspensión sin goce de sueldo', 'dismissal' => 'Terminar la relación de trabajo, según la Ley Federal del Trabajo', 'support' => 'Canalizar a apoyo psicológico'];

    private const array PROTECTION = ['no_retaliation' => 'No hay represalias por denunciar', 'identity' => 'Su identidad solo la conoce quien atiende', 'transfer' => 'Puede pedir un cambio de área temporal mientras se revisa'];

    public function version(): string
    {
        return 'questions-2026-11-05';
    }

    public function name(): string
    {
        return 'Violencia laboral';
    }

    public function title(): string
    {
        return 'Medidas contra la violencia laboral';
    }

    public function summary(): string
    {
        return 'Qué se considera violencia, qué pasa al denunciar y cómo se protege a quien denuncia.';
    }

    public function minutes(): int
    {
        return 5;
    }

    public function questions(): array
    {
        return [
            new Question('considered', 'Violencia laboral', '¿Qué consideran violencia laboral?', [
                new Field('considered', 'Qué consideran violencia', self::CONSIDERED, multiple: true, default: ['shouting', 'humiliation', 'harassment']),
            ], 'Marca todas las que apliquen. En la revisión puedes sumar las tuyas.'),
            new Question('report', 'Al denunciar', '¿Qué pasa cuando alguien la denuncia?', [
                new Field('report', 'Qué pasa', self::REPORT, default: 'acknowledge'),
            ], 'Que coincida con los plazos de tu canal de quejas.'),
            new Question('measures', 'Medidas', '¿Qué medidas puede tomar la empresa?', [
                new Field('measures', 'Medidas', self::MEASURES, multiple: true, default: ['warning', 'transfer']),
            ], 'Según la gravedad. Deben coincidir con tu reglamento interior.'),
            new Question('protection', 'Protección', '¿Cómo protegen a quien denuncia?', [
                new Field('protection', 'Cómo', self::PROTECTION, multiple: true, default: ['no_retaliation', 'identity']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $report = $answers->phrase('report', self::REPORT);

        return [
            new Section('considered', 'Qué consideramos violencia', Sentence::of($answers->list('considered', self::lower(self::CONSIDERED)))),
            new Section('report', 'Si usted la denuncia', $report === null ? null : "{$report}."),
            new Section('measures', 'Qué puede pasar', Sentence::of($answers->list('measures', self::lower(self::MEASURES)))),
            new Section('protection', 'Cómo le protegemos', Sentence::of($answers->list('protection', self::lower(self::PROTECTION)))),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, string> The labels in the lower case of the middle of a sentence.
     */
    private static function lower(array $labels): array
    {
        return array_map(fn (string $label): string => mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1), $labels);
    }
}
