/** «¿Te aplica?» of NOM-035 and Ley Silla (board V2-Aplica-Otras): the choices and what each answer means. */
export type Range = 'up_to_15' | '16_to_50' | 'over_50';

export type SectorValue = 'commerce' | 'services' | 'industrial' | 'analogous';

export const RANGES: { value: Range; label: string }[] = [
    { value: 'up_to_15', label: 'Hasta 15 personas' },
    { value: '16_to_50', label: 'De 16 a 50 personas' },
    { value: 'over_50', label: 'Más de 50 personas' },
];

export const SECTORS: { value: SectorValue; label: string }[] = [
    { value: 'commerce', label: 'Comercio' },
    { value: 'services', label: 'Servicios' },
    { value: 'industrial', label: 'Industrial' },
    { value: 'analogous', label: 'Otro parecido' },
];

export type Result = {
    tag: string;
    title: string;
    body: string;
    extra: string;
};

/**
 * What NOM-035 asks of a center of that size: the Guía I to everyone in each evaluation (criterio C-04), and the Guía II
 * from 16 people or the III above 50 (num. 4.4).
 */
export function nom035Result(range: Range, people: number): Result {
    switch (range) {
        case 'up_to_15':
            return {
                tag: 'Te aplica',
                title: 'Guía I para todas las personas',
                body: 'En centros de hasta 15 personas no se aplican los cuestionarios de factores de riesgo. Todas contestan la Guía I, que identifica a quien haya vivido un acontecimiento traumático severo.',
                extra: 'La política de prevención y compartirla con tu equipo siguen siendo obligatorias.',
            };
        case '16_to_50':
            return {
                tag: 'Te aplica',
                title: 'Guías I y II para todas las personas',
                body: 'Todas contestan la Guía I (acontecimientos traumáticos) y la Guía II (factores de riesgo psicosocial).',
                extra: 'Tu ruta tendrá 6 etapas, igual que en los demás centros.',
            };
        default:
            return {
                tag: 'Te aplica',
                title: 'Guías I y III',
                body: 'Todas contestan la Guía I. La Guía III, más completa, la contesta todo el centro o una muestra que calculamos por ti.',
                extra:
                    people > 0
                        ? `Con ${people} personas, la muestra mínima la ves al crear la evaluación.`
                        : 'La muestra mínima la ves al crear la evaluación.',
            };
    }
}

/**
 * What Ley Silla asks of a center by its answers: nothing where nobody works standing; in an industrial center, a seat only
 * where the work allows it (criterio C-03); elsewhere, in every standing position. `applies` is null until it is known.
 */
export function seatingResult(
    standing: boolean | null,
    sector: SectorValue | null,
): Result & { applies: boolean | null } {
    if (standing === null) {
        return {
            tag: 'Contesta para ver el resultado',
            title: 'Primero, ¿alguien trabaja de pie?',
            body: 'Con una o dos respuestas sabes si la Ley Silla te aplica y en qué puestos.',
            extra: '',
            applies: null,
        };
    }

    if (!standing) {
        return {
            tag: 'No te aplica',
            title: 'Nadie trabaja de pie en este centro',
            body: 'Guardamos la declaración con fecha y tu nombre. Es lo que muestras si te la piden en una inspección.',
            extra: 'Si cambia, por ejemplo abres un mostrador, vuelve a contestar.',
            applies: false,
        };
    }

    if (sector === null) {
        return {
            tag: 'Casi',
            title: 'Falta saber a qué se dedica',
            body: 'El sector decide si aplica en todos los puestos o solo en algunos.',
            extra: '',
            applies: null,
        };
    }

    return sector === 'industrial'
        ? {
              tag: 'Te aplica en algunos puestos',
              title: 'Solo donde el trabajo permite sentarse',
              body: 'En un centro industrial, la ley pide asiento donde la naturaleza del trabajo lo permite. Lo decides puesto por puesto y dejas la razón por escrito.',
              extra: 'Tu ruta empieza por identificar los puestos de pie.',
              applies: true,
          }
        : {
              tag: 'Te aplica',
              title: 'En todos los puestos de pie',
              body: 'Cada persona que trabaja de pie debe tener un asiento con respaldo y pausas para descansar.',
              extra: 'Tu ruta empieza por identificar los puestos de pie.',
              applies: true,
          };
}
