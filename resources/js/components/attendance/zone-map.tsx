import { useState } from 'react';
import type { MouseEvent } from 'react';
import ZoneProbeNote from '@/components/attendance/zone-probe-note';
import {
    layoutZones,
    pointAt,
    probeAt,
    scaleBar,
} from '@/lib/attendance/zone-map';
import type { MapSize, MapZone, Probe } from '@/lib/attendance/zone-map';

const SIZE: MapSize = { width: 640, height: 320 };

/**
 * The plan of a center's zones (board A2): each zone a circle to scale with its name, a scale bar and the test «¿contaría una
 * marca aquí?» for any point touched. It is a plan, not a street map: it draws only the zones. The table of zones carries the
 * same data for a screen reader.
 */
export default function ZoneMap({ zones }: { zones: MapZone[] }) {
    const [probe, setProbe] = useState<Probe | null>(null);
    const layout = layoutZones(zones, SIZE);
    const bar = scaleBar(layout.metersPerUnit, SIZE.width);

    const touch = (event: MouseEvent<SVGSVGElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const unit = SIZE.width / box.width;

        setProbe(
            probeAt(
                zones,
                pointAt(
                    {
                        x: (event.clientX - box.left) * unit,
                        y: (event.clientY - box.top) * unit,
                    },
                    layout,
                ),
            ),
        );
    };

    return (
        <div className="flex flex-col gap-3">
            <svg
                role="img"
                aria-label={`Plano de ${zones.length} ${zones.length === 1 ? 'zona' : 'zonas'} a escala`}
                viewBox={`0 0 ${SIZE.width} ${SIZE.height}`}
                className="h-auto w-full cursor-crosshair rounded-xl border bg-muted"
                onClick={touch}
            >
                {layout.shapes.map((shape) => (
                    <g key={shape.id}>
                        <circle
                            cx={shape.cx}
                            cy={shape.cy}
                            r={shape.r}
                            className="fill-primary/15 stroke-primary"
                            strokeWidth={2}
                        />
                        <text
                            x={shape.cx}
                            y={shape.cy}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            className="fill-foreground text-sm font-semibold"
                        >
                            {shape.name}
                        </text>
                    </g>
                ))}
                <g transform={`translate(16 ${SIZE.height - 24})`}>
                    <rect
                        width={bar.width}
                        height={4}
                        className="fill-muted-foreground"
                    />
                    <text y={-6} className="fill-muted-foreground text-caption">
                        {bar.label}
                    </text>
                </g>
            </svg>
            <ZoneProbeNote probe={probe} />
        </div>
    );
}
