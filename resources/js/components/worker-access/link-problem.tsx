import { Link } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import StateMessage from '@/components/worker-access/state-message';
import { Button } from '@/components/ui/button';

/** A link that does not work (used, expired, replaced or unknown): one screen for every cause, and the way to a new one. */
export default function LinkProblem({
    company,
    homeUrl,
}: {
    company: string;
    homeUrl: string;
}) {
    return (
        <WorkerFrame
            company={company}
            centered
            actions={
                <Button asChild className="h-14 w-full text-lg font-semibold">
                    <Link href={homeUrl}>Pedir un enlace nuevo</Link>
                </Button>
            }
        >
            <StateMessage
                icon={Link2Off}
                tone="blocked"
                title="Este enlace ya no sirve"
                role="alert"
            >
                Ya se usó o venció. No pasó nada con sus datos. Pida un enlace
                nuevo o entre con su huella.
            </StateMessage>
        </WorkerFrame>
    );
}
