import { Camera, Users } from 'lucide-react';
import type { DisseminationRecordRow } from '@/types';

/** A means recorded (board V4-Difusion-Registro): what it was, where, what it covered and when, and its file to see. */
export default function MediaRow({
    record,
}: {
    record: DisseminationRecordRow;
}) {
    return (
        <li className="flex items-center gap-3 px-4 py-3">
            <span
                aria-hidden
                className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            >
                {record.signatures === null ? (
                    <Camera className="size-4" />
                ) : (
                    <Users className="size-4" />
                )}
            </span>
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="font-medium">
                    {record.mediumLabel}
                    {record.signatures !== null &&
                        ` · ${record.signatures === 1 ? '1 firma' : `${record.signatures} firmas`}`}
                </span>
                <span className="text-sm text-muted-foreground">
                    {record.workCenter} · cubrió {record.items.join(', ')} ·{' '}
                    {record.doneOn}
                </span>
            </span>
            {record.evidence !== null && (
                <a
                    href={record.evidence}
                    target="_blank"
                    rel="noopener"
                    className="touch-hit text-sm font-medium text-primary"
                >
                    Ver
                    <span className="sr-only">
                        {' '}
                        la evidencia de {record.mediumLabel}
                    </span>
                </a>
            )}
        </li>
    );
}
