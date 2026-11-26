import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarPlus,
    Mail,
    MonitorPlay,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import DiscardDemoDialog from '@/components/demos/discard-demo-dialog';
import ScheduleDemoDialog from '@/components/demos/schedule-demo-dialog';
import { Button } from '@/components/ui/button';
import { index as companies, show } from '@/routes/companies';
import { show as room } from '@/routes/demo-room';
import type { DemoRequestRow } from '@/types';

/**
 * What the team does next with a request, in a column (tablero Demos): answer by email, schedule its demo, open the
 * demo room, create the company with its data or discard it with a reason. A converted request leads to its company;
 * support only answers.
 */
export default function DemoRequestActions({
    request,
    canManage,
}: {
    request: DemoRequestRow;
    canManage: boolean;
}) {
    const { can_open_room: canOpenRoom } = usePage<{
        can_open_room: boolean;
    }>().props;
    const [dialog, setDialog] = useState<'schedule' | 'discard' | null>(null);
    const close = (open: boolean) => setDialog(open ? dialog : null);
    const open = request.tenant_id === null && canManage;

    return (
        // In a column, each action reads from the left, as the board draws them.
        <div className="flex flex-col gap-2.5 border-t px-5 py-4 *:justify-start md:px-6">
            <Button asChild>
                <a href={`mailto:${request.email}`}>
                    <Mail aria-hidden />
                    Responder por correo
                </a>
            </Button>
            {request.tenant_id !== null && (
                <Button asChild variant="outline">
                    <Link href={show(request.tenant_id)}>
                        <Building2 aria-hidden />
                        Ver empresa
                    </Link>
                </Button>
            )}
            {open && (
                <>
                    <Button
                        variant="outline"
                        onClick={() => setDialog('schedule')}
                    >
                        <CalendarPlus aria-hidden />
                        Agendar demo
                    </Button>
                    {canOpenRoom && (
                        <Button asChild variant="outline">
                            <Link href={room()}>
                                <MonitorPlay aria-hidden />
                                Abrir sala de demo
                            </Link>
                        </Button>
                    )}
                    <Button asChild variant="outline">
                        <Link
                            href={companies.url({
                                query: { demo: request.id },
                            })}
                        >
                            <Building2 aria-hidden />
                            Crear empresa con estos datos
                        </Link>
                    </Button>
                    {request.status !== 'discarded' && (
                        <Button
                            variant="ghost"
                            className="text-primary"
                            onClick={() => setDialog('discard')}
                        >
                            <XCircle aria-hidden />
                            Descartar con motivo
                        </Button>
                    )}
                    <ScheduleDemoDialog
                        request={request}
                        open={dialog === 'schedule'}
                        onOpenChange={close}
                    />
                    <DiscardDemoDialog
                        request={request}
                        open={dialog === 'discard'}
                        onOpenChange={close}
                    />
                </>
            )}
        </div>
    );
}
