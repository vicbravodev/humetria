import ArcMeter from '@/components/humetria/arc-meter';
import GrowBar from '@/components/landing/grow-bar';

const areas = [
    { name: 'Producción', answered: 58, of: 64 },
    { name: 'Administración', answered: 19, of: 21 },
    { name: 'Almacén', answered: 12, of: 35 },
] as const;

/** A Guía III campaign: how many answered, and the answers by area. */
export default function VisualNom035() {
    return (
        <div className="flex flex-col items-center gap-6 sm:flex-row sm:items-center">
            <ArcMeter
                value={89}
                max={120}
                label="89 de 120 contestaron"
                size="md"
            >
                <span className="text-[30px] leading-none font-semibold tabular-nums">
                    89
                </span>
                <span className="text-caption text-muted-foreground">
                    de 120
                </span>
            </ArcMeter>
            <ul className="flex w-full flex-col gap-3">
                {areas.map((area, index) => (
                    <li key={area.name} className="flex flex-col gap-1.5">
                        <span className="flex justify-between text-sm">
                            <span>{area.name}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {area.answered} de {area.of}
                            </span>
                        </span>
                        <GrowBar
                            share={area.answered / area.of}
                            delay={index * 0.1}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
