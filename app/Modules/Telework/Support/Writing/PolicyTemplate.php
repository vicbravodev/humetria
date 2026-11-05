<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The Política de Teletrabajo (num. 5.2 a–l) written with eight questions (board V2-Politica). Each section has the key of
 * the inciso it covers. The first four questions, their options and their text are the board's; c, f, j and k are the
 * examples of the DOF's Apéndice 1 (informativo), literal, as the base text RH may edit.
 *
 * TODO-LEGAL: a lawyer validates every text here before production: the board's wording (e, b, d, h), the questions and
 * wording Humetría added (a, g, i, l, marked below) and whether the Apéndice 1 examples (c, f, j, k) fit the company.
 */
final readonly class PolicyTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Política de Teletrabajo';
    }

    public function title(): string
    {
        return 'Política de Teletrabajo';
    }

    public function summary(): string
    {
        return 'Las reglas del teletrabajo en tus centros: horario, desconexión, contacto, lactancia y los 12 temas del numeral 5.2.';
    }

    public function minutes(): int
    {
        return 12;
    }

    public function questions(): array
    {
        return [
            new Question('e', 'Horario y desconexión', '¿En qué horario trabajan y desde cuándo nadie debe recibir mensajes de trabajo?', [
                new Field('schedule', 'Horario', ['nine' => 'De 9:00 a 18:00', 'eight' => 'De 8:00 a 17:00', 'flexible' => 'Horario flexible de 8 horas'], default: 'nine', shown: true),
                new Field('disconnect', 'Desconexión', ['end' => 'Al terminar su horario', 'half' => '30 minutos después de su horario', 'hour' => 'Una hora después de su horario'], default: 'end', shown: true),
            ]),
            new Question('b', 'Contacto con el equipo', '¿Cada cuánto se reúne el equipo para que nadie se quede aislado?', [
                new Field('frequency', 'Frecuencia', ['daily' => 'Todos los días', 'weekly' => 'Una vez por semana', 'biweekly' => 'Cada 15 días']),
            ], 'La norma pide que la persona no pierda contacto con su equipo.'),
            new Question('d', 'Medios de contacto y privacidad', '¿Por qué medios puede la empresa contactar a alguien en teletrabajo?', [
                new Field('channels', 'Medios', ['email' => 'Correo de la empresa', 'phone' => 'Teléfono', 'chat' => 'Chat de la empresa', 'video' => 'Videollamada'], multiple: true, default: ['email', 'chat'], columns: 2),
            ], 'Elige todos los que usan. Lo demás queda fuera para cuidar su privacidad.'),
            new Question('h', 'Lactancia', '¿Cómo se respeta la lactancia en teletrabajo?', [
                new Field('lactation', 'Lactancia', ['breaks' => 'Dos descansos de 30 minutos al día', 'hour' => 'Una hora menos de jornada al día']),
            ], 'La Ley Federal del Trabajo da a elegir entre estas dos formas.'),
            // TODO-LEGAL: from here on, the questions and options are Humetría's (the board shows four of the eight).
            new Question('a', 'Cultura de prevención', '¿Cada cuánto le hablas a tu equipo de los riesgos del teletrabajo?', [
                new Field('prevention', 'Frecuencia', ['yearly' => 'Una vez al año, en la capacitación', 'semester' => 'Cada seis meses', 'quarter' => 'Cada tres meses']),
            ], 'La norma pide promover una cultura de prevención de riesgos.'),
            new Question('g', 'Género y vida personal', '¿Con quién acuerda alguien un horario que le ayude con su vida personal?', [
                new Field('balance', 'Con quién', ['boss' => 'Con su jefe directo', 'hr' => 'Con Recursos Humanos']),
            ], 'La norma pide promover la perspectiva de género y la conciliación con la vida personal.'),
            new Question('i', 'Salud', '¿Cómo cuida la empresa la salud de quien teletrabaja?', [
                new Field('health', 'Cómo', ['exams' => 'Exámenes médicos periódicos', 'breaks' => 'Pausas activas durante la jornada', 'support' => 'Apoyo psicológico', 'campaigns' => 'Campañas de salud'], multiple: true),
            ], 'Elige todo lo que ofrecen.'),
            new Question('l', 'Regreso a la oficina', '¿Con cuánta anticipación se pide volver a la oficina o empezar a teletrabajar?', [
                new Field('notice', 'Anticipación', ['twenty' => 'Con 20 días de anticipación', 'fifteen' => 'Con 15 días', 'thirty' => 'Con 30 días']),
            ], 'El cambio siempre se acuerda entre la persona y la empresa.'),
        ];
    }

    public function sections(Answers $answers): array
    {
        $schedule = $answers->phrase('schedule', ['nine' => 'de 9:00 a 18:00', 'eight' => 'de 8:00 a 17:00', 'flexible' => 'de 8 horas, en un horario flexible']);
        $disconnect = $answers->phrase('disconnect', ['end' => 'Al terminar su horario', 'half' => 'A partir de 30 minutos después de su horario', 'hour' => 'A partir de una hora después de su horario']);
        $frequency = $answers->phrase('frequency', ['daily' => 'todos los días', 'weekly' => 'una vez por semana', 'biweekly' => 'cada 15 días']);
        $channels = $answers->list('channels', ['email' => 'correo de la empresa', 'phone' => 'teléfono', 'chat' => 'chat de la empresa', 'video' => 'videollamada']);
        // TODO-LEGAL: the board leaves out the six months of 5.2 h) and art. 170 LFT.
        $lactation = $answers->phrase('lactation', ['breaks' => 'dos descansos de 30 minutos al día para alimentar a su bebé o extraer leche', 'hour' => 'una hora menos de jornada al día']);
        $prevention = $answers->phrase('prevention', ['yearly' => 'una vez al año, en la capacitación', 'semester' => 'cada seis meses', 'quarter' => 'cada tres meses']);
        $balance = $answers->phrase('balance', ['boss' => 'con su jefe directo', 'hr' => 'con Recursos Humanos']);
        $health = $answers->list('health', ['exams' => 'exámenes médicos periódicos', 'breaks' => 'pausas activas durante la jornada', 'support' => 'apoyo psicológico', 'campaigns' => 'campañas de salud']);
        $notice = $answers->phrase('notice', ['twenty' => '20', 'fifteen' => '15', 'thirty' => '30']);

        return [
            new Section('e', 'Horario y desconexión', $schedule === null || $disconnect === null ? null : "La jornada en teletrabajo es {$schedule}, de lunes a viernes. {$disconnect}, nadie está obligado a responder mensajes ni llamadas de trabajo."),
            new Section('b', 'Contacto con el equipo', $frequency === null ? null : "Para evitar el aislamiento, el equipo se reúne {$frequency}, por videollamada o en persona."),
            new Section('d', 'Medios de contacto', $channels === null ? null : "La empresa se comunica con las personas en teletrabajo solo por {$channels}, dentro de su horario y respetando su privacidad."),
            new Section('h', 'Lactancia', $lactation === null ? null : "Las personas en periodo de lactancia tienen {$lactation}, con goce de sueldo."),
            // TODO-LEGAL: the wording of a, g, i and l is Humetría's.
            new Section('a', 'Prevención de riesgos', $prevention === null ? null : "La empresa informa a las personas en teletrabajo sobre los riesgos de su trabajo {$prevention}, para que exista una cultura de prevención de riesgos y accidentes."),
            new Section('g', 'Género y vida personal', $balance === null ? null : "Las personas en teletrabajo pueden pedir horarios escalonados que les ayuden con las responsabilidades del hogar y su vida personal. Se acuerdan {$balance}, con las mismas condiciones y cargas de trabajo sin importar el género."),
            new Section('i', 'Salud', $health === null ? null : "La empresa promueve y vigila la salud de las personas en teletrabajo con {$health}."),
            new Section('l', 'Regreso a la oficina', $notice === null ? null : "El cambio del trabajo presencial al teletrabajo, o al revés, se acuerda entre la persona y la empresa y se pide con {$notice} días de anticipación. La empresa hace todo lo posible para que la persona pueda volver a trabajar en el centro."),
            // DOF 08/06/2023, Apéndice 1 (informativo), incisos C, F, J y K, literal.
            new Section('c', 'Comunicación de procedimientos', 'Las personas trabajadoras bajo la modalidad de teletrabajo deberán utilizar las herramientas de comunicación y mensajería (software), proporcionada por el centro trabajo, mismas que serán los mecanismos oficiales de comunicación entre el centro de trabajo y las personas que teletrabajen.', base: true),
            new Section('f', 'Mismos derechos', 'Las personas trabajadoras bajo la modalidad de Teletrabajo del centro de trabajo gozarán del mismo trato que el personal en la modalidad presencial, por lo que todas las prestaciones y beneficios otorgados a estos serán también conferidos a las personas que teletrabajen.', base: true),
            new Section('j', 'Cambios de domicilio', 'El cambio o adición de un nuevo lugar de trabajo para las personas que trabajen en la modalidad de teletrabajo deberá ser solicitado por la persona teletrabajadora al menos con quince días de anticipación, con el fin de que se lleven a cabo los procesos necesarios para validar que dicho lugar cumple con las condiciones de seguridad y salud necesarias para el óptimo desempeño del teletrabajo.', base: true),
            new Section('k', 'Responsabilidades', "El centro trabajo se compromete a otorgar el equipo de cómputo y/o dispositivos tecnológicos necesarios para el desarrollo del teletrabajo, así como a brindar la asistencia y capacitación necesarias para la instalación y correcto uso de estos.\n\nLas personas trabajadoras bajo la modalidad de Teletrabajo se obligan a tener el mayor cuidado en la guarda y conservación de los equipos, materiales, útiles o mobiliario ergonómico que reciban del centro de trabajo, y deberán firmar la carta responsiva que se les otorgue para cada uno de dichos objetos.", base: true),
        ];
    }
}
