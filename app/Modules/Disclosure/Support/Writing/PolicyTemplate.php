<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;
use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The policy of prevention (num. 5.1) written with six questions (board V2-N035-Politica; decisión A3). It stays the example
 * of Guía de referencia IV (criterio C-51): the commitments are its literal base text, and the principles are the guide's,
 * literal, keeping only the ones RH says are true in its centers. The guide's note asks for the responsibles and the
 * resources, so two questions write them. The words keep `{legal_name}`, put in on display.
 *
 * TODO-LEGAL: a lawyer validates, before production, the wording Humetría adds: who signs (the board's), the responsibles, the
 * resources and how it is made known (the board's), and the plain labels of the principles. The guide's text is not changed:
 * only the marks of its lists (numbers, letters, the «y» before the last one) follow what is kept.
 */
final readonly class PolicyTemplate implements WrittenTemplate
{
    /** The principles after the first, by option, as Guía IV numbers them. */
    private const array PRINCIPLES = [
        'p2' => 'Todas las personas son importantes para el centro de trabajo',
        'p3' => 'Capacitación conforme a la Ley Federal del Trabajo',
        'p4' => 'Manuales de organización y descripciones de puesto',
        'p5' => 'Tableros con anuncios y avisos para todos',
        'p6' => 'Cargas de trabajo y jornadas conforme a la ley',
        'p7' => 'Evaluación del desempeño al menos una vez al año',
        'p8' => 'Vigilar la prevención de la violencia y atender las quejas a tiempo',
    ];

    /** The practices of the first principle (its incisos a to h), in plain words. */
    private const array PRACTICES = [
        'a' => 'Reuniones para que todos expongan sus dudas, inquietudes y quejas',
        'b' => 'Áreas de trabajo y áreas comunes limpias',
        'c' => 'Trato amable y cortés entre todos',
        'd' => 'Se atienden y sancionan burlas, malos tratos y discriminación',
        'e' => 'Exámenes médicos al personal de nuevo ingreso',
        'f' => 'Exámenes psicológicos o psicométricos para ubicar a cada quien en su puesto',
        'g' => 'Planes de promoción interna',
        'h' => 'Capacitación de todo el personal',
    ];

    public function __construct(private Nom035Policy $guide) {}

    public function version(): string
    {
        return 'questions-2026-11-05';
    }

    public function name(): string
    {
        return 'Política de prevención';
    }

    public function title(): string
    {
        return 'Política de prevención de riesgos psicosociales';
    }

    public function summary(): string
    {
        return 'Los compromisos y principios de la Guía de referencia IV que son ciertos en tus centros, con quién responde de ellos y con qué recursos.';
    }

    public function minutes(): int
    {
        return 10;
    }

    public function questions(): array
    {
        return [
            new Question('signature', 'Compromiso', '¿Quién firma la política en nombre de la empresa?', [
                new Field('signature', 'Quién firma', ['direction' => 'La dirección general', 'hr' => 'La dirección de Recursos Humanos', 'owner' => 'El dueño o la dueña'], default: 'direction'),
            ], 'Quien firma muestra que el compromiso viene de arriba.'),
            new Question('responsibles', 'Responsables', '¿Quién responde de aplicarla?', [
                new Field('responsibles', 'Responsables', ['direction' => 'La dirección general', 'hr' => 'Recursos Humanos', 'supervisors' => 'Supervisores, gerentes y jefes de área', 'committee' => 'La Comisión de seguridad e higiene'], multiple: true),
            ], 'La Guía de referencia IV pide nombrar a quien tiene capacidad para aplicarla. Elige todos los que respondan de ella.'),
            new Question('resources', 'Recursos', '¿Con qué recursos cuentas para cumplirla?', [
                new Field('resources', 'Recursos', ['channel' => 'Un canal confidencial de quejas y denuncias', 'training' => 'Capacitación para el personal', 'staff' => 'Personal de Recursos Humanos que la atiende', 'health' => 'Servicio médico o apoyo psicológico', 'budget' => 'Un presupuesto para las acciones de prevención'], multiple: true),
            ], 'Elige lo que de verdad tienes: la política se lee ante tus personas y ante la STPS.'),
            new Question('practices', 'Entorno favorable', '¿Qué de esto se hace en tus centros?', [
                new Field('practices', 'Prácticas', self::PRACTICES, multiple: true),
            ], 'Es el primer principio de la Guía de referencia IV. Deja fuera lo que no hagas.'),
            new Question('principles', 'Principios', '¿Qué otros principios son ciertos en tus centros?', [
                new Field('principles', 'Principios', self::PRINCIPLES, multiple: true),
            ], 'Son los de la Guía de referencia IV, con su texto completo. Elige solo los que cumples.'),
            new Question('dissemination', 'Difusión', '¿Cómo la vas a dar a conocer?', [
                new Field('dissemination', 'Cómo', ['page' => 'En la página de las personas, donde confirman que la leyeron', 'board' => 'En un mural o pizarrón', 'induction' => 'En la inducción de quien entra', 'meetings' => 'En reuniones de equipo'], multiple: true, default: ['page']),
            ], 'La página de las personas deja constancia de quién la leyó.'),
        ];
    }

    public function sections(Answers $answers): array
    {
        $guide = $this->guide();
        $signature = $answers->phrase('signature', ['direction' => 'La firma la dirección general.', 'hr' => 'La firma la dirección de Recursos Humanos.', 'owner' => 'La firma el dueño o la dueña de la empresa.']);
        $responsibles = $answers->list('responsibles', ['direction' => 'la dirección general', 'hr' => 'Recursos Humanos', 'supervisors' => 'los supervisores, gerentes y jefes de área', 'committee' => 'la Comisión de seguridad e higiene']);
        $resources = $answers->list('resources', ['channel' => 'un canal confidencial de quejas y denuncias', 'training' => 'capacitación para el personal', 'staff' => 'personal de Recursos Humanos que la atiende', 'health' => 'servicio médico o apoyo psicológico', 'budget' => 'un presupuesto para las acciones de prevención']);
        $practices = $this->kept($guide['principle_1']->items, array_keys(self::PRACTICES), $answers->many('practices'));
        $principles = array_map(fn (string $key): string => $guide['principle_'.substr($key, 1)]->text, array_values(array_intersect(array_keys(self::PRINCIPLES), $answers->many('principles'))));
        $dissemination = $answers->list('dissemination', ['page' => 'en la página de las personas trabajadoras, donde cada persona confirma que la leyó', 'board' => 'en un mural o pizarrón', 'induction' => 'en la inducción de quien entra', 'meetings' => 'en reuniones de equipo']);

        return [
            new Section('commitments', $guide['commitments']->heading, self::listed($guide['commitments']->text, $guide['commitments']->items, numbers: true), base: true),
            new Section('signature', 'Quién la firma', $signature),
            new Section('responsibles', 'Responsables', $responsibles === null ? null : "Son responsables de aplicar esta política: {$responsibles}. Reciben la capacitación para hacerlo."),
            new Section('resources', 'Recursos', $resources === null ? null : "Para cumplir esta política, la empresa dispone de {$resources}."),
            new Section('practices', 'Entorno organizacional favorable', $practices === [] ? null : self::listed($guide['principle_1']->text, $practices, numbers: false)),
            new Section('principles', 'Principios', $principles === [] ? null : implode("\n\n", [$guide['principles']->text, ...$principles])),
            new Section('dissemination', 'Cómo la damos a conocer', $dissemination === null ? null : "Esta política se comparte {$dissemination}."),
        ];
    }

    /** @return array<string, PolicySection> The sections of Guía IV by key. */
    private function guide(): array
    {
        $sections = [];

        foreach ($this->guide->template()->sections as $section) {
            $sections[$section->key] = $section;
        }

        return $sections;
    }

    /**
     * The items of the guide whose option was chosen, in the guide's order.
     *
     * @param  list<string>  $items
     * @param  list<string>  $options  The option of each item, in the same order.
     * @param  list<string>  $chosen
     * @return list<string>
     */
    private function kept(array $items, array $options, array $chosen): array
    {
        return array_values(array_filter($items, fn (int $index): bool => in_array($options[$index] ?? '', $chosen, true), ARRAY_FILTER_USE_KEY));
    }

    /**
     * A text with its list under it, a paragraph per item: numbered (1.) or lettered (a)), each closed the way the guide closes
     * them, «;» and «, y» before the last one, which ends in a period.
     *
     * @param  list<string>  $items
     */
    private static function listed(string $text, array $items, bool $numbers): string
    {
        $count = count($items);
        $lines = array_map(function (int $index, string $item) use ($count, $numbers): string {
            $mark = $numbers ? ($index + 1).'.' : (range('a', 'z')[$index] ?? '').')';
            $end = match (true) {
                $index === $count - 1 => '.',
                $index === $count - 2 => ', y',
                default => ';',
            };

            return "{$mark} ".preg_replace('/(, y|;|\.)$/u', '', $item).$end;
        }, array_keys($items), $items);

        return implode("\n\n", [$text, ...$lines]);
    }
}
