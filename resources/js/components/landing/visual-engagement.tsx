import { EyeOff } from 'lucide-react';
import GrowBar from '@/components/landing/grow-bar';

const drivers = [
    { name: 'Liderazgo', score: 78 },
    { name: 'Reconocimiento', score: 64 },
    { name: 'Carga de trabajo', score: 52 },
] as const;

/** What drives engagement, with the group too small to show left out. */
export default function VisualEngagement() {
    return (
        <div className="flex flex-col gap-4 card p-4">
            <p className="text-sm font-medium">Qué impulsa el compromiso</p>
            <ul className="flex flex-col gap-3">
                {drivers.map((driver, index) => (
                    <li key={driver.name} className="flex flex-col gap-1.5">
                        <span className="flex justify-between gap-3 text-sm">
                            <span>{driver.name}</span>
                            <span className="font-medium tabular-nums">
                                {driver.score} %
                            </span>
                        </span>
                        <GrowBar
                            share={driver.score / 100}
                            delay={index * 0.12}
                        />
                    </li>
                ))}
            </ul>
            <p className="flex items-center gap-2 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground">
                <EyeOff aria-hidden className="size-4 shrink-0" />
                Almacén: oculto, menos de 5 respuestas
            </p>
        </div>
    );
}
