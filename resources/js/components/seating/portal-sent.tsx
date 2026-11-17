import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import PortalDone from '@/components/seating/portal-done';
import PortalFrame from '@/components/seating/portal-frame';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/calendar-date';
import { show } from '@/routes/portal/seating';

/** After the two questions (board SillaEnviado): it was sent, when, and what happens with it; the person may change them. */
export default function PortalSent({
    slug,
    company,
    sentOn,
    onAgain,
}: {
    slug: string;
    company: string;
    sentOn: string;
    onAgain: () => void;
}) {
    return (
        <PortalFrame
            company={company}
            privacy="identified"
            centered
            actions={
                <>
                    <Button
                        className="h-14 w-full text-lg font-semibold"
                        asChild
                    >
                        <Link href={show.url(slug)}>Volver a Ley Silla</Link>
                    </Button>
                    <Button
                        variant="ghost"
                        className="h-12 w-full text-lg font-medium text-primary"
                        onClick={onAgain}
                    >
                        Cambiar mis respuestas
                    </Button>
                </>
            }
        >
            <PortalDone icon={CircleCheck} title="Gracias, quedó enviado">
                Nos contestó el {shortDate(sentOn)}. La Comisión lo tomará en
                cuenta en su evaluación.
            </PortalDone>
        </PortalFrame>
    );
}
