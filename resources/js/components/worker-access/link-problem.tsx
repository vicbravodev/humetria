import { Link } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import ToneTile from '@/components/humetria/tone-tile';
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
            actions={
                <Button asChild className="h-14 w-full text-lg font-semibold">
                    <Link href={homeUrl}>Pedir un enlace nuevo</Link>
                </Button>
            }
        >
            <section role="alert" className="flex flex-col items-start gap-3">
                <ToneTile icon={Link2Off} tone="blocked" large />
                <h1 className="text-2xl leading-8 font-semibold text-balance">
                    Este enlace ya no sirve
                </h1>
                <p className="text-muted-foreground">
                    Ya se usó o venció. No pasó nada con sus datos. Pida un
                    enlace nuevo o entre con su huella.
                </p>
            </section>
        </WorkerFrame>
    );
}
