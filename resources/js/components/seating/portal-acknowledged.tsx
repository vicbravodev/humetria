import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import PortalDone from '@/components/seating/portal-done';
import PortalFrame from '@/components/seating/portal-frame';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/calendar-date';
import { show } from '@/routes/portal/seating';

/** Once the person confirmed (board SillaAcuseHecho): when, centered, and the way back to their Ley Silla tasks. */
export default function PortalAcknowledged({
    slug,
    company,
    acknowledgedOn,
}: {
    slug: string;
    company: string;
    acknowledgedOn: string;
}) {
    return (
        <PortalFrame
            company={company}
            privacy="identified"
            centered
            actions={
                <Button className="h-14 w-full text-lg font-semibold" asChild>
                    <Link href={show.url(slug)}>Volver a Ley Silla</Link>
                </Button>
            }
        >
            <PortalDone icon={ShieldCheck} title="Gracias, quedó registrado">
                Confirmó el {shortDate(acknowledgedOn)} que le informaron del
                resultado de su evaluación.
            </PortalDone>
        </PortalFrame>
    );
}
