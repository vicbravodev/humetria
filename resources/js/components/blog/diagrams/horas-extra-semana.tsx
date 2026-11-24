import DiagramFrame from '@/components/blog/diagrams/diagram-frame';

const DAYS = [3, 3, 3, 2] as const;
const DOUBLE_LIMIT = 9;

/** The article's example: 11 overtime hours in one week of 2027, the first 9 paid double and the last 2 triple (the article's reading). */
export default function HorasExtraSemana() {
    const hours = DAYS.flatMap((count, day) =>
        Array.from({ length: count }, (_, index) => ({
            key: `${day}-${index}`,
        })),
    );

    return (
        <DiagramFrame
            title="Una semana de 2027 con 11 horas extra: 9 dobles y 2 triples"
            source="Ejemplo del artículo, con la lectura de Humetría del artículo 68: el tope de 2027 es de 9 horas extra dobles."
        >
            <ol className="grid gap-2 sm:grid-cols-4">
                {DAYS.map((count, day) => (
                    <li
                        key={`day-${count}-${day}`}
                        className="rounded-xl bg-family-soft p-3"
                    >
                        <p className="text-caption text-muted-foreground">
                            Día {day + 1}
                        </p>
                        <p className="text-lg font-semibold">
                            {count} horas extra
                        </p>
                    </li>
                ))}
            </ol>
            <ul
                aria-label="Las 11 horas, en orden: 9 pagadas al doble y 2 al triple"
                className="mt-4 flex flex-wrap gap-1.5"
            >
                {hours.map((hour, index) => (
                    <li
                        key={hour.key}
                        className={
                            index < DOUBLE_LIMIT
                                ? 'flex h-9 w-9 items-center justify-center rounded-md bg-family text-caption font-semibold text-background'
                                : 'flex h-9 w-9 items-center justify-center rounded-md border-2 border-family bg-background text-caption font-semibold text-family'
                        }
                    >
                        {index < DOUBLE_LIMIT ? '×2' : '×3'}
                    </li>
                ))}
            </ul>
        </DiagramFrame>
    );
}
