import { Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import PortalChecklistController from '@/actions/App/Modules/Telework/Http/Controllers/PortalChecklistController';
import { Button } from '@/components/ui/button';
import type { PortalChecklist } from '@/types';

/** One checklist of a workplace: where it is, which section and where it stands (pantalla: Encuesta-Movil). */
export default function PortalChecklistCard({
    checklist,
    token,
    pending,
}: {
    checklist: PortalChecklist;
    token: string;
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
                {pending && (
                    <Button asChild className="mt-2 h-12 self-start text-base">
                        <Link
                            href={PortalChecklistController.show.url({
                                token,
                                application: checklist.id,
                            })}
                        >
                            Contestar
                        </Link>
                    </Button>
                )}
            </div>
        </li>
    );
}
