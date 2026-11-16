import { router } from '@inertiajs/react';
import { useState } from 'react';
import { MailCheck } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import PersonHeading from '@/components/humetria/person-heading';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** A one-time link that still works: «Continuar» spends it (opening it does not, so a mail filter cannot use it up). */
export default function LinkContinue({
    company,
    openUrl,
}: {
    company: string;
    openUrl: string;
}) {
    const [working, setWorking] = useState(false);
    const open = () =>
        router.post(
            openUrl,
            {},
            {
                onStart: () => setWorking(true),
                onFinish: () => setWorking(false),
            },
        );

    return (
        <WorkerFrame
            company={company}
            actions={
                <Button
                    className="h-14 w-full text-lg font-semibold"
                    disabled={working}
                    onClick={open}
                >
                    {working && <Spinner className="size-6" />}
                    Continuar
                </Button>
            }
        >
            <ToneTile icon={MailCheck} large />
            <PersonHeading title="Su enlace está listo">
                Toque «Continuar» para entrar. El enlace sirve una sola vez.
            </PersonHeading>
        </WorkerFrame>
    );
}
