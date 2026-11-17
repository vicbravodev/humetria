import { Head, Link } from '@inertiajs/react';
import { PartyPopper } from 'lucide-react';
import PortalHome from '@/components/telework/portal/portal-home';
import PortalShell from '@/components/telework/portal/portal-shell';
import { Button } from '@/components/ui/button';
import StateMessage from '@/components/worker-access/state-message';
import WorkerFrame from '@/components/worker-access/worker-frame';
import { nothingPending } from '@/lib/telework/portal';
import { show } from '@/routes/portal/notices';
import { home } from '@/routes/worker';
import type { PortalPending } from '@/types';

/** The person's telework section, in their session. */
export default function PortalSection({
    slug,
    company,
    canNotify,
    pending,
}: {
    slug: string;
    company: string;
    canNotify: boolean;
    pending: PortalPending;
}) {
    if (nothingPending(pending)) {
        return (
            <WorkerFrame
                company={company}
                centered
                back={home.url(slug)}
                actions={
                    canNotify ? (
                        <Button
                            asChild
                            variant="outline"
                            className="h-14 w-full text-lg font-semibold"
                        >
                            <Link href={show.url(slug)}>
                                Sus avisos por escrito
                            </Link>
                        </Button>
                    ) : undefined
                }
            >
                <Head title="Su teletrabajo" />
                <StateMessage
                    icon={PartyPopper}
                    tone="ok"
                    title="Nada pendiente por ahora"
                >
                    Cuando su empresa le pida algo, lo verá aquí y le avisaremos
                    por correo. Si necesita algo antes, pida a Recursos Humanos
                    de su empresa.
                </StateMessage>
            </WorkerFrame>
        );
    }

    return (
        <PortalShell company={company}>
            <Head title="Su teletrabajo" />
            <PortalHome slug={slug} canNotify={canNotify} pending={pending} />
        </PortalShell>
    );
}
