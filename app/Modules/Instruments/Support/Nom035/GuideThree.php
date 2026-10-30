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
        return new InstrumentDefinition(self::KEY, Nom035::VERSION, 'Factores de riesgo psicosocial y entorno organizacional', false, Likert::options(), [
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
}
