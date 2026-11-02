import { Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import PortalChecklistController from '@/actions/App/Modules/Telework/Http/Controllers/PortalChecklistController';
import { Button } from '@/components/ui/button';
import type { PortalChecklist } from '@/types';
import PortalCard from '@/components/telework/portal/portal-card';

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
        <PortalCard
            icon={ClipboardList}
            title={
                checklist.section === 1
                    ? 'Lista de verificación de su lugar de trabajo'
                    : 'Revisión periódica de su lugar de trabajo'
            }
            detail={checklist.workplace}
        >
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
        </PortalCard>
    );
}
