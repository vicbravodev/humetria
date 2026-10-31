import { Send } from 'lucide-react';
import ArcMeter from '@/components/humetria/arc-meter';
import ProgressBar from '@/components/humetria/progress-bar';
import { TOOLS } from '@/components/landing/landing-tools';
import type { ToolKey } from '@/components/landing/landing-tools';
import { Badge } from '@/components/ui/badge';

/** How far each tool has come in the example work center, out of 100. */
const progress: Readonly<Record<ToolKey, number>> = {
    nom_035: 82,
    nom_037: 68,
    ley_silla: 55,
};

/** A still picture of a work center's board: where each tool stands, never an individual answer. */
export default function HeroPreview() {
    return (
        <figure
            data-test="hero-preview"
            className="@container flex w-full max-w-[460px] flex-col gap-[18px] rounded-lg border bg-card p-[22px] shadow-float"
        >
            <figcaption className="sr-only">
                Ejemplo del tablero de un centro de trabajo en Humetría
            </figcaption>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">Planta Querétaro</p>
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        120 personas · 3 herramientas
                    </p>
                </div>
                <Badge>En curso</Badge>
            </div>
            <div className="flex flex-col items-center gap-6 @[22rem]:flex-row @[22rem]:items-end">
                <ArcMeter
                    value={89}
                    max={120}
                    label="89 de 120 contestaron"
                    size="md"
                >
                    <span className="text-[34px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        89
                    </span>
                    <span className="max-w-[85px] text-[13px] leading-4 text-muted-foreground">
                        de 120 contestaron
                    </span>
                </ArcMeter>
                <ul className="flex w-full flex-col gap-3 pb-1 @[22rem]:w-auto @[22rem]:min-w-0 @[22rem]:flex-1">
                    {TOOLS.map((tool) => (
                        <li
                            key={tool.key}
                            data-test={`preview-${tool.key}`}
                            className="flex flex-col gap-1"
                        >
                            <span className="text-[13px] leading-[18px] text-muted-foreground">
                                {tool.name}
                            </span>
                            <ProgressBar
                                value={progress[tool.key]}
                                max={100}
                                label={`Avance de ${tool.name}`}
                            />
                        </li>
                    ))}
                </ul>
            </div>
            <p className="flex items-center gap-2.5 border-t pt-3.5 text-sm text-muted-foreground">
                <Send aria-hidden className="size-4 shrink-0 text-primary" />
                Recordatorio enviado · llegará a 31 personas
            </p>
        </figure>
    );
}
