import ProgressBar from '@/components/humetria/progress-bar';
import type { EvidenceCenter } from '@/types';

/** How many confirmed the policy in force in each center of the page (board V4-Difusion-Registro), with its bar. */
export default function AckCenterBars({
    centers,
}: {
    centers: EvidenceCenter[];
}) {
    return (
        <ul className="flex flex-col gap-4">
            {centers.map((center) => (
                <li key={center.id} className="flex flex-col gap-1.5">
                    <p className="flex justify-between gap-3 text-sm">
                        <span className="font-medium">{center.name}</span>
                        <span className="text-muted-foreground">
                            {center.acks === null
                                ? 'Sin política vigente'
                                : `${center.acks.confirmed} de ${center.acks.active}`}
                        </span>
                    </p>
                    {center.acks !== null && (
                        <ProgressBar
                            value={center.acks.confirmed}
                            max={center.acks.active}
                            label={`Confirmaron en ${center.name}`}
                        />
                    )}
                </li>
            ))}
        </ul>
    );
}
