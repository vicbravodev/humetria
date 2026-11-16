import { Fingerprint } from 'lucide-react';
import EntryLinkForm from '@/components/worker-access/entry-link-form';
import StatusLine from '@/components/worker-access/status-line';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { useWorkerEntry } from '@/hooks/use-worker-entry';
import { entryMessage } from '@/lib/worker-access/entry';

/**
 * The screen to enter (CUJ 2, 3 and 8): with the key of this device, or with a link by email. It names nobody: whoever
 * entered on this device before leaves no trace here.
 */
export default function EntryScreen({
    company,
    entry,
}: {
    company: string;
    entry: ReturnType<typeof useWorkerEntry>;
}) {
    return (
        <WorkerFrame
            company={company}
            actions={
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={entry.working}
                    onClick={() => void entry.enter()}
                >
                    {entry.working ? (
                        <Spinner className="size-6" />
                    ) : (
                        <Fingerprint aria-hidden className="size-6" />
                    )}
                    Entrar con mi huella
                </Button>
            }
        >
            <PersonHeading title="Entre a su portal">
                Use la huella, el rostro o el PIN de este dispositivo. No
                necesita contraseña.
            </PersonHeading>
            <section className="flex items-center gap-4 card p-5">
                <ToneTile icon={Fingerprint} />
                <p className="text-base text-muted-foreground">
                    Su dispositivo le pedirá confirmar que es usted.
                </p>
            </section>
            {entry.failure !== null && (
                <StatusLine text={entryMessage(entry.failure)} problem />
            )}
            <EntryLinkForm
                working={entry.working}
                outcome={entry.linkOutcome}
                onAsk={(email) => void entry.askLink(email)}
            />
        </WorkerFrame>
    );
}
