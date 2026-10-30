<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\SectionDefinition;

/**
 * NOM-035 Guía de referencia III, for work centers of more than 50 workers: the questionnaire, literal from the DOF (docs/normas/nom-035/texto-de-referencia.md).
 */
final class GuideThree
{
    public const string KEY = 'nom035.guide-iii';

    public static function definition(): InstrumentDefinition
    {
        return new InstrumentDefinition(self::KEY, Nom035::VERSION, 'Factores de riesgo psicosocial y entorno organizacional', 'Guía III', false, Likert::options(), [
            new SectionDefinition(null, 'Para responder las preguntas siguientes considere las condiciones ambientales de su centro de trabajo.', Nom035::items([
                1 => 'El espacio donde trabajo me permite realizar mis actividades de manera segura e higiénica',
                2 => 'Mi trabajo me exige hacer mucho esfuerzo físico',
                3 => 'Me preocupa sufrir un accidente en mi trabajo',
                4 => 'Considero que en mi trabajo se aplican las normas de seguridad y salud en el trabajo',
                5 => 'Considero que las actividades que realizo son peligrosas',
            ])),
            new SectionDefinition(null, 'Para responder a las preguntas siguientes piense en la cantidad y ritmo de trabajo que tiene.', Nom035::items([
                6 => 'Por la cantidad de trabajo que tengo debo quedarme tiempo adicional a mi turno',
                7 => 'Por la cantidad de trabajo que tengo debo trabajar sin parar',
                8 => 'Considero que es necesario mantener un ritmo de trabajo acelerado',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con el esfuerzo mental que le exige su trabajo.', Nom035::items([
                9 => 'Mi trabajo exige que esté muy concentrado',
                10 => 'Mi trabajo requiere que memorice mucha información',
                11 => 'En mi trabajo tengo que tomar decisiones difíciles muy rápido',
                12 => 'Mi trabajo exige que atienda varios asuntos al mismo tiempo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con las actividades que realiza en su trabajo y las responsabilidades que tiene.', Nom035::items([
                13 => 'En mi trabajo soy responsable de cosas de mucho valor',
                14 => 'Respondo ante mi jefe por los resultados de toda mi área de trabajo',
                15 => 'En el trabajo me dan órdenes contradictorias',
                16 => 'Considero que en mi trabajo me piden hacer cosas innecesarias',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con su jornada de trabajo.', Nom035::items([
                17 => 'Trabajo horas extras más de tres veces a la semana',
                18 => 'Mi trabajo me exige laborar en días de descanso, festivos o fines de semana',
                19 => 'Considero que el tiempo en el trabajo es mucho y perjudica mis actividades familiares o personales',
                20 => 'Debo atender asuntos de trabajo cuando estoy en casa',
                21 => 'Pienso en las actividades familiares o personales cuando estoy en mi trabajo',
                22 => 'Pienso que mis responsabilidades familiares afectan mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con las decisiones que puede tomar en su trabajo.', Nom035::items([
                23 => 'Mi trabajo permite que desarrolle nuevas habilidades',
                24 => 'En mi trabajo puedo aspirar a un mejor puesto',
                25 => 'Durante mi jornada de trabajo puedo tomar pausas cuando las necesito',
                26 => 'Puedo decidir cuánto trabajo realizo durante la jornada laboral',
                27 => 'Puedo decidir la velocidad a la que realizo mis actividades en mi trabajo',
                28 => 'Puedo cambiar el orden de las actividades que realizo en mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con cualquier tipo de cambio que ocurra en su trabajo (considere los últimos cambios realizados).', Nom035::items([
                29 => 'Los cambios que se presentan en mi trabajo dificultan mi labor',
                30 => 'Cuando se presentan cambios en mi trabajo se tienen en cuenta mis ideas o aportaciones',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con la capacitación e información que se le proporciona sobre su trabajo.', Nom035::items([
                31 => 'Me informan con claridad cuáles son mis funciones',
                32 => 'Me explican claramente los resultados que debo obtener en mi trabajo',
                33 => 'Me explican claramente los objetivos de mi trabajo',
                34 => 'Me informan con quién puedo resolver problemas o asuntos de trabajo',
                35 => 'Me permiten asistir a capacitaciones relacionadas con mi trabajo',
                36 => 'Recibo capacitación útil para hacer mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con el o los jefes con quien tiene contacto.', Nom035::items([
                37 => 'Mi jefe ayuda a organizar mejor el trabajo',
                38 => 'Mi jefe tiene en cuenta mis puntos de vista y opiniones',
                39 => 'Mi jefe me comunica a tiempo la información relacionada con el trabajo',
                40 => 'La orientación que me da mi jefe me ayuda a realizar mejor mi trabajo',
                41 => 'Mi jefe ayuda a solucionar los problemas que se presentan en el trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes se refieren a las relaciones con sus compañeros.', Nom035::items([
                42 => 'Puedo confiar en mis compañeros de trabajo',
                43 => 'Entre compañeros solucionamos los problemas de trabajo de forma respetuosa',
                44 => 'En mi trabajo me hacen sentir parte del grupo',
                45 => 'Cuando tenemos que realizar trabajo de equipo los compañeros colaboran',
                46 => 'Mis compañeros de trabajo me ayudan cuando tengo dificultades',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con la información que recibe sobre su rendimiento en el trabajo, el reconocimiento, el sentido de pertenencia y la estabilidad que le ofrece su trabajo.', Nom035::items([
                47 => 'Me informan sobre lo que hago bien en mi trabajo',
                48 => 'La forma como evalúan mi trabajo en mi centro de trabajo me ayuda a mejorar mi desempeño',
                49 => 'En mi centro de trabajo me pagan a tiempo mi salario',
                50 => 'El pago que recibo es el que merezco por el trabajo que realizo',
                51 => 'Si obtengo los resultados esperados en mi trabajo me recompensan o reconocen',
                52 => 'Las personas que hacen bien el trabajo pueden crecer laboralmente',
                53 => 'Considero que mi trabajo es estable',
                54 => 'En mi trabajo existe continua rotación de personal',
                55 => 'Siento orgullo de laborar en este centro de trabajo',
                56 => 'Me siento comprometido con mi trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con actos de violencia laboral (malos tratos, acoso, hostigamiento, acoso psicológico).', Nom035::items([
                57 => 'En mi trabajo puedo expresarme libremente sin interrupciones',
                58 => 'Recibo críticas constantes a mi persona y/o trabajo',
                59 => 'Recibo burlas, calumnias, difamaciones, humillaciones o ridiculizaciones',
                60 => 'Se ignora mi presencia o se me excluye de las reuniones de trabajo y en la toma de decisiones',
                61 => 'Se manipulan las situaciones de trabajo para hacerme parecer un mal trabajador',
                62 => 'Se ignoran mis éxitos laborales y se atribuyen a otros trabajadores',
                63 => 'Me bloquean o impiden las oportunidades que tengo para obtener ascenso o mejora en mi trabajo',
                64 => 'He presenciado actos de violencia en mi centro de trabajo',
            ])),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con la atención a clientes y usuarios.', Nom035::items([
                65 => 'Atiendo clientes o usuarios muy enojados',
                66 => 'Mi trabajo me exige atender personas muy necesitadas de ayuda o enfermas',
                67 => 'Para hacer mi trabajo debo demostrar sentimientos distintos a los míos',
                68 => 'Mi trabajo me exige atender situaciones de violencia',
            ]), Nom035::clientsGate()),
            new SectionDefinition(null, 'Las preguntas siguientes están relacionadas con las actitudes de las personas que supervisa.', Nom035::items([
                69 => 'Comunican tarde los asuntos de trabajo',
                70 => 'Dificultan el logro de los resultados del trabajo',
                71 => 'Cooperan poco cuando se necesita',
                72 => 'Ignoran las sugerencias para mejorar su trabajo',
            ]), Nom035::supervisionGate()),
        ]);
    }

    /**
     * Tablas 5 and 6 and the ranges of III.3 c: which items score 0 on "Siempre", how they group, and the ranges of each level.
     */
    public static function scoring(): ScoringTable
    {
        return new ScoringTable(
            zeroOnAlways: [1, 4, 23, 24, 25, 26, 27, 28, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 55, 56, 57],
            dimensions: [
                'dangerous_conditions' => ['label' => 'Condiciones peligrosas e inseguras', 'domain' => 'environment_conditions', 'items' => [1, 3]],
                'deficient_conditions' => ['label' => 'Condiciones deficientes e insalubres', 'domain' => 'environment_conditions', 'items' => [2, 4]],
                'hazardous_work' => ['label' => 'Trabajos peligrosos', 'domain' => 'environment_conditions', 'items' => [5]],
                'quantitative_load' => ['label' => 'Cargas cuantitativas', 'domain' => 'workload', 'items' => [6, 12]],
                'accelerated_pace' => ['label' => 'Ritmos de trabajo acelerado', 'domain' => 'workload', 'items' => [7, 8]],
                'mental_load' => ['label' => 'Carga mental', 'domain' => 'workload', 'items' => [9, 10, 11]],
                'emotional_load' => ['label' => 'Cargas psicológicas emocionales', 'domain' => 'workload', 'items' => [65, 66, 67, 68]],
                'high_responsibility' => ['label' => 'Cargas de alta responsabilidad', 'domain' => 'workload', 'items' => [13, 14]],
                'contradictory_load' => ['label' => 'Cargas contradictorias o inconsistentes', 'domain' => 'workload', 'items' => [15, 16]],
                'lack_of_autonomy' => ['label' => 'Falta de control y autonomía sobre el trabajo', 'domain' => 'lack_of_control', 'items' => [25, 26, 27, 28]],
                'limited_development' => ['label' => 'Limitada o nula posibilidad de desarrollo', 'domain' => 'lack_of_control', 'items' => [23, 24]],
                'change_management' => ['label' => 'Insuficiente participación y manejo del cambio', 'domain' => 'lack_of_control', 'items' => [29, 30]],
                'limited_training' => ['label' => 'Limitada o inexistente capacitación', 'domain' => 'lack_of_control', 'items' => [35, 36]],
                'long_hours' => ['label' => 'Jornadas de trabajo extensas', 'domain' => 'work_day', 'items' => [17, 18]],
                'work_outside' => ['label' => 'Influencia del trabajo fuera del centro laboral', 'domain' => 'work_family', 'items' => [19, 20]],
                'family_responsibilities' => ['label' => 'Influencia de las responsabilidades familiares', 'domain' => 'work_family', 'items' => [21, 22]],
                'unclear_roles' => ['label' => 'Escasa claridad de funciones', 'domain' => 'leadership', 'items' => [31, 32, 33, 34]],
                'leadership_traits' => ['label' => 'Características del liderazgo', 'domain' => 'leadership', 'items' => [37, 38, 39, 40, 41]],
                'social_relations' => ['label' => 'Relaciones sociales en el trabajo', 'domain' => 'relations', 'items' => [42, 43, 44, 45, 46]],
                'supervised_relations' => ['label' => 'Deficiente relación con los colaboradores que supervisa', 'domain' => 'relations', 'items' => [69, 70, 71, 72]],
                'workplace_violence' => ['label' => 'Violencia laboral', 'domain' => 'violence', 'items' => [57, 58, 59, 60, 61, 62, 63, 64]],
                'performance_feedback' => ['label' => 'Escasa o nula retroalimentación del desempeño', 'domain' => 'performance_recognition', 'items' => [47, 48]],
                'recognition_compensation' => ['label' => 'Escaso o nulo reconocimiento y compensación', 'domain' => 'performance_recognition', 'items' => [49, 50, 51, 52]],
                'limited_belonging' => ['label' => 'Limitado sentido de pertenencia', 'domain' => 'belonging_instability', 'items' => [55, 56]],
                'job_instability' => ['label' => 'Inestabilidad laboral', 'domain' => 'belonging_instability', 'items' => [53, 54]],
            ],
            domains: [
                'environment_conditions' => ['label' => 'Condiciones en el ambiente de trabajo', 'category' => 'work_environment', 'cutoffs' => [5, 9, 11, 14]],
                'workload' => ['label' => 'Carga de trabajo', 'category' => 'activity_factors', 'cutoffs' => [15, 21, 27, 37]],
                'lack_of_control' => ['label' => 'Falta de control sobre el trabajo', 'category' => 'activity_factors', 'cutoffs' => [11, 16, 21, 25]],
                'work_day' => ['label' => 'Jornada de trabajo', 'category' => 'work_time', 'cutoffs' => [1, 2, 4, 6]],
                'work_family' => ['label' => 'Interferencia en la relación trabajo-familia', 'category' => 'work_time', 'cutoffs' => [4, 6, 8, 10]],
                'leadership' => ['label' => 'Liderazgo', 'category' => 'leadership_relations', 'cutoffs' => [9, 12, 16, 20]],
                'relations' => ['label' => 'Relaciones en el trabajo', 'category' => 'leadership_relations', 'cutoffs' => [10, 13, 17, 21]],
                'violence' => ['label' => 'Violencia', 'category' => 'leadership_relations', 'cutoffs' => [7, 10, 13, 16]],
                'performance_recognition' => ['label' => 'Reconocimiento del desempeño', 'category' => 'organizational_environment', 'cutoffs' => [6, 10, 14, 18]],
                'belonging_instability' => ['label' => 'Insuficiente sentido de pertenencia e inestabilidad', 'category' => 'organizational_environment', 'cutoffs' => [4, 6, 8, 10]],
            ],
            categories: [
                'work_environment' => ['label' => 'Ambiente de trabajo', 'cutoffs' => [5, 9, 11, 14]],
                'activity_factors' => ['label' => 'Factores propios de la actividad', 'cutoffs' => [15, 30, 45, 60]],
                'work_time' => ['label' => 'Organización del tiempo de trabajo', 'cutoffs' => [5, 7, 10, 13]],
                'leadership_relations' => ['label' => 'Liderazgo y relaciones en el trabajo', 'cutoffs' => [14, 29, 42, 58]],
                'organizational_environment' => ['label' => 'Entorno organizacional', 'cutoffs' => [10, 14, 18, 23]],
            ],
            final: [50, 75, 99, 140],
        );
    }
}
