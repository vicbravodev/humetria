<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\SectionDefinition;

/**
 * NOM-035 Guía de referencia II, for work centers of 16 to 50 workers: the questionnaire, literal from the DOF (docs/normas/nom-035/texto-de-referencia.md).
 */
final class GuideTwo
{
    public const string KEY = 'nom035.guide-ii';

    public static function definition(): InstrumentDefinition
    {
        return new InstrumentDefinition(self::KEY, Nom035::VERSION, 'Factores de riesgo psicosocial', 'Guía II', false, Likert::options(), [
            new SectionDefinition(null, 'Para responder las preguntas siguientes considere las condiciones de su centro de trabajo, así como la cantidad y ritmo de trabajo.', Nom035::items([
                1 => 'Mi trabajo me exige hacer mucho esfuerzo físico',
                2 => 'Me preocupa sufrir un accidente en mi trabajo',
                3 => 'Considero que las actividades que realizo son peligrosas',
                4 => 'Por la cantidad de trabajo que tengo debo quedarme tiempo adicional a mi turno',
                5 => 'Por la cantidad de trabajo que tengo debo trabajar sin parar',
                6 => 'Considero que es necesario mantener un ritmo de trabajo acelerado',
                7 => 'Mi trabajo exige que esté muy concentrado',
                8 => 'Mi trabajo requiere que memorice mucha información',
                9 => 'Mi trabajo exige que atienda varios asuntos al mismo tiempo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con las actividades que realiza en su trabajo y las responsabilidades que tiene.', Nom035::items([
                10 => 'En mi trabajo soy responsable de cosas de mucho valor',
                11 => 'Respondo ante mi jefe por los resultados de toda mi área de trabajo',
                12 => 'En mi trabajo me dan órdenes contradictorias',
                13 => 'Considero que en mi trabajo me piden hacer cosas innecesarias',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con el tiempo destinado a su trabajo y sus responsabilidades familiares.', Nom035::items([
                14 => 'Trabajo horas extras más de tres veces a la semana',
                15 => 'Mi trabajo me exige laborar en días de descanso, festivos o fines de semana',
                16 => 'Considero que el tiempo en el trabajo es mucho y perjudica mis actividades familiares o personales',
                17 => 'Pienso en las actividades familiares o personales cuando estoy en mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con las decisiones que puede tomar en su trabajo.', Nom035::items([
                18 => 'Mi trabajo permite que desarrolle nuevas habilidades',
                19 => 'En mi trabajo puedo aspirar a un mejor puesto',
                20 => 'Durante mi jornada de trabajo puedo tomar pausas cuando las necesito',
                21 => 'Puedo decidir la velocidad a la que realizo mis actividades en mi trabajo',
                22 => 'Puedo cambiar el orden de las actividades que realizo en mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con la capacitación e información que recibe sobre su trabajo.', Nom035::items([
                23 => 'Me informan con claridad cuáles son mis funciones',
                24 => 'Me explican claramente los resultados que debo obtener en mi trabajo',
                25 => 'Me informan con quién puedo resolver problemas o asuntos de trabajo',
                26 => 'Me permiten asistir a capacitaciones relacionadas con mi trabajo',
                27 => 'Recibo capacitación útil para hacer mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes se refieren a las relaciones con sus compañeros de trabajo y su jefe.', Nom035::items([
                28 => 'Mi jefe tiene en cuenta mis puntos de vista y opiniones',
                29 => 'Mi jefe ayuda a solucionar los problemas que se presentan en el trabajo',
                30 => 'Puedo confiar en mis compañeros de trabajo',
                31 => 'Cuando tenemos que realizar trabajo de equipo los compañeros colaboran',
                32 => 'Mis compañeros de trabajo me ayudan cuando tengo dificultades',
                33 => 'En mi trabajo puedo expresarme libremente sin interrupciones',
                34 => 'Recibo críticas constantes a mi persona y/o trabajo',
                35 => 'Recibo burlas, calumnias, difamaciones, humillaciones o ridiculizaciones',
                36 => 'Se ignora mi presencia o se me excluye de las reuniones de trabajo y en la toma de decisiones',
                37 => 'Se manipulan las situaciones de trabajo para hacerme parecer un mal trabajador',
                38 => 'Se ignoran mis éxitos laborales y se atribuyen a otros trabajadores',
                39 => 'Me bloquean o impiden las oportunidades que tengo para obtener ascenso o mejora en mi trabajo',
                40 => 'He presenciado actos de violencia en mi centro de trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con la atención a clientes y usuarios.', Nom035::items([
                41 => 'Atiendo clientes o usuarios muy enojados',
                42 => 'Mi trabajo me exige atender personas muy necesitadas de ayuda o enfermas',
                43 => 'Para hacer mi trabajo debo demostrar sentimientos distintos a los míos',
            ]), Nom035::clientsGate()),
            new SectionDefinition(null, 'Las siguientes preguntas están relacionadas con las actitudes de los trabajadores que supervisa.', Nom035::items([
                44 => 'Comunican tarde los asuntos de trabajo',
                45 => 'Dificultan el logro de los resultados del trabajo',
                46 => 'Ignoran las sugerencias para mejorar su trabajo',
            ]), Nom035::supervisionGate()),
        ], minutes: 10);
    }

    /**
     * Tablas 2 and 3 and the ranges of II.3 c: which items score 0 on "Siempre", how they group, and the ranges of each level.
     */
    public static function scoring(): ScoringTable
    {
        return new ScoringTable(
            zeroOnAlways: [18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33],
            dimensions: [
                'dangerous_conditions' => ['label' => 'Condiciones peligrosas e inseguras', 'domain' => 'environment_conditions', 'items' => [2]],
                'deficient_conditions' => ['label' => 'Condiciones deficientes e insalubres', 'domain' => 'environment_conditions', 'items' => [1]],
                'hazardous_work' => ['label' => 'Trabajos peligrosos', 'domain' => 'environment_conditions', 'items' => [3]],
                'quantitative_load' => ['label' => 'Cargas cuantitativas', 'domain' => 'workload', 'items' => [4, 9]],
                'accelerated_pace' => ['label' => 'Ritmos de trabajo acelerado', 'domain' => 'workload', 'items' => [5, 6]],
                'mental_load' => ['label' => 'Carga mental', 'domain' => 'workload', 'items' => [7, 8]],
                'emotional_load' => ['label' => 'Cargas psicológicas emocionales', 'domain' => 'workload', 'items' => [41, 42, 43]],
                'high_responsibility' => ['label' => 'Cargas de alta responsabilidad', 'domain' => 'workload', 'items' => [10, 11]],
                'contradictory_load' => ['label' => 'Cargas contradictorias o inconsistentes', 'domain' => 'workload', 'items' => [12, 13]],
                'lack_of_autonomy' => ['label' => 'Falta de control y autonomía sobre el trabajo', 'domain' => 'lack_of_control', 'items' => [20, 21, 22]],
                'limited_development' => ['label' => 'Limitada o nula posibilidad de desarrollo', 'domain' => 'lack_of_control', 'items' => [18, 19]],
                'limited_training' => ['label' => 'Limitada o inexistente capacitación', 'domain' => 'lack_of_control', 'items' => [26, 27]],
                'long_hours' => ['label' => 'Jornadas de trabajo extensas', 'domain' => 'work_day', 'items' => [14, 15]],
                'work_outside' => ['label' => 'Influencia del trabajo fuera del centro laboral', 'domain' => 'work_family', 'items' => [16]],
                'family_responsibilities' => ['label' => 'Influencia de las responsabilidades familiares', 'domain' => 'work_family', 'items' => [17]],
                'unclear_roles' => ['label' => 'Escasa claridad de funciones', 'domain' => 'leadership', 'items' => [23, 24, 25]],
                'leadership_traits' => ['label' => 'Características del liderazgo', 'domain' => 'leadership', 'items' => [28, 29]],
                'social_relations' => ['label' => 'Relaciones sociales en el trabajo', 'domain' => 'relations', 'items' => [30, 31, 32]],
                'supervised_relations' => ['label' => 'Deficiente relación con los colaboradores que supervisa', 'domain' => 'relations', 'items' => [44, 45, 46]],
                'workplace_violence' => ['label' => 'Violencia laboral', 'domain' => 'violence', 'items' => [33, 34, 35, 36, 37, 38, 39, 40]],
            ],
            domains: [
                'environment_conditions' => ['label' => 'Condiciones en el ambiente de trabajo', 'category' => 'work_environment', 'cutoffs' => [3, 5, 7, 9]],
                'workload' => ['label' => 'Carga de trabajo', 'category' => 'activity_factors', 'cutoffs' => [12, 16, 20, 24]],
                'lack_of_control' => ['label' => 'Falta de control sobre el trabajo', 'category' => 'activity_factors', 'cutoffs' => [5, 8, 11, 14]],
                'work_day' => ['label' => 'Jornada de trabajo', 'category' => 'work_time', 'cutoffs' => [1, 2, 4, 6]],
                'work_family' => ['label' => 'Interferencia en la relación trabajo-familia', 'category' => 'work_time', 'cutoffs' => [1, 2, 4, 6]],
                'leadership' => ['label' => 'Liderazgo', 'category' => 'leadership_relations', 'cutoffs' => [3, 5, 8, 11]],
                'relations' => ['label' => 'Relaciones en el trabajo', 'category' => 'leadership_relations', 'cutoffs' => [5, 8, 11, 14]],
                'violence' => ['label' => 'Violencia', 'category' => 'leadership_relations', 'cutoffs' => [7, 10, 13, 16]],
            ],
            categories: [
                'work_environment' => ['label' => 'Ambiente de trabajo', 'cutoffs' => [3, 5, 7, 9]],
                'activity_factors' => ['label' => 'Factores propios de la actividad', 'cutoffs' => [10, 20, 30, 40]],
                'work_time' => ['label' => 'Organización del tiempo de trabajo', 'cutoffs' => [4, 6, 9, 12]],
                'leadership_relations' => ['label' => 'Liderazgo y relaciones en el trabajo', 'cutoffs' => [10, 18, 28, 38]],
            ],
            final: [20, 45, 70, 90],
        );
    }
}
