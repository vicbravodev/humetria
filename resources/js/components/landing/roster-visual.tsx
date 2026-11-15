import { FileSpreadsheet } from 'lucide-react';
import { ICON_BOX } from '@/components/landing/landing-layout';
import StepBar from '@/components/landing/step-bar';

const centers = [
    { name: 'Oficinas Monterrey', share: 1 },
    { name: 'Planta Querétaro', share: 0.74 },
    { name: 'Bodega Toluca', share: 0.3 },
];

/** Step 1: the roster you upload, already split by workplace. */
export default function RosterVisual() {
    return (
        <div className="grid gap-4">
            <div className="flex items-center gap-3.5">
                <span className={`${ICON_BOX} h-13 w-11 rounded-lg`}>
                    <FileSpreadsheet aria-hidden className="size-[22px]" />
                </span>
                <span>
                    <b className="block font-semibold">plantilla.xlsx</b>
                    <span className="text-meta text-muted-foreground">
                        <b className="font-semibold text-foreground tabular-nums">
                            120
                        </b>{' '}
                        personas
                    </span>
                </span>
            </div>
            <ul className="grid gap-2.5 text-sm">
                {centers.map((center, index) => (
                    <li
                        key={center.name}
                        className="grid grid-cols-[minmax(0,1fr)_minmax(70px,38%)] items-center gap-3"
                    >
                        <span>{center.name}</span>
                        <StepBar
                            value={center.share}
                            delay={0.3 + index * 0.08}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
