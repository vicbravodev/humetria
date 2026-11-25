import { Link, usePage } from '@inertiajs/react';
import { Building2, CalendarPlus, MonitorPlay, XCircle } from 'lucide-react';
import { useState } from 'react';
import DiscardDemoDialog from '@/components/demos/discard-demo-dialog';
import ScheduleDemoDialog from '@/components/demos/schedule-demo-dialog';
import { Button } from '@/components/ui/button';
import { index as companies, show } from '@/routes/companies';
import { show as room } from '@/routes/demo-room';
import type { DemoRequestRow } from '@/types';

/**
 * What the team does next with a request: schedule its demo, open the demo room, create the company with its data or
 * discard it with a reason. A converted request only leads to its company; support reads and changes nothing.
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

    if (request.tenant_id !== null) {
        return (
            <Button asChild size="sm" className="w-fit">
                <Link href={show(request.tenant_id)}>
                    <Building2 aria-hidden />
                    Ver empresa
                </Link>
            </Button>
        );
    }

    if (!canManage) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            <Button size="sm" onClick={() => setDialog('schedule')}>
                <CalendarPlus aria-hidden />
                Agendar demo
            </Button>
            {canOpenRoom && (
                <Button asChild size="sm" variant="outline">
                    <Link href={room()}>
                        <MonitorPlay aria-hidden />
                        Abrir sala de demo
                    </Link>
                </Button>
            )}
            <Button asChild size="sm" variant="outline">
                <Link href={companies.url({ query: { demo: request.id } })}>
                    <Building2 aria-hidden />
                    Crear empresa con estos datos
                </Link>
            </Button>
            {request.status !== 'discarded' && (
                <Button
                    size="sm"
                    variant="ghost"
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
        </div>
    );
}
