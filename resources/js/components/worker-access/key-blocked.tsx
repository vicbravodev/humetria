import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { keyProblem } from '@/lib/worker-access/key';

/** A session that may not add a key here: entered with a link the person asked for, or with 3 keys already. */
export default function KeyBlocked({
    company,
    reason,
    homeUrl,
}: {
    company: string;
    reason: 'forbidden' | 'full';
    homeUrl: string;
}) {
    const { title, text } = keyProblem(reason);

    return (
        <WorkerFrame
            company={company}
            actions={
                <Button asChild className="h-14 w-full text-lg font-semibold">
                    <Link href={homeUrl}>Volver a mi portal</Link>
                </Button>
            }
        >
            <section className="flex flex-col items-start gap-3">
                <ToneTile icon={KeyRound} tone="warning" large />
                <h1 className="text-2xl leading-8 font-semibold text-balance">
                    {title}
                </h1>
                <p className="text-muted-foreground">{text}</p>
            </section>
        </WorkerFrame>
    );
}
