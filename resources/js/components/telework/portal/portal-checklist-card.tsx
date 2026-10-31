import { ClipboardList } from 'lucide-react';
import type { PortalChecklist } from '@/types';

/** One checklist of a workplace: where it is, which section and where it stands (pantalla: Encuesta-Movil). */
export default function PortalChecklistCard({
    checklist,
    pending,
}: {
    checklist: PortalChecklist;
    pending: boolean;
}) {
    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <ClipboardList aria-hidden className="size-5" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">
                    {checklist.section === 1
                        ? 'Lista de verificación de su lugar de trabajo'
                        : 'Revisión periódica de su lugar de trabajo'}
                </p>
                <p className="text-base text-muted-foreground">
                    {checklist.workplace}
                </p>
                <p
                    className={
                        pending
                            ? 'mt-1 text-base font-medium text-primary'
                            : 'mt-1 text-base text-muted-foreground'
                    }
                >
                    {checklist.label}
                </p>
            </div>
        </li>
    );
}
