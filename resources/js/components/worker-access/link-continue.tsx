import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { MailCheck } from 'lucide-react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import StateMessage from '@/components/worker-access/state-message';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** A one-time link that still works: «Continuar» spends it (opening it does not, so a mail filter cannot use it up). */
export default function LinkContinue({ company }: { company: string }) {
    const { url } = usePage();
    const [working, setWorking] = useState(false);
    const open = () =>
        router.post(
            url,
            {},
            {
                onStart: () => setWorking(true),
                onFinish: () => setWorking(false),
            },
        );

    return (
        <WorkerFrame
            company={company}
            centered
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
            <StateMessage icon={MailCheck} title="Su enlace está listo">
                Toque «Continuar» para entrar. El enlace sirve una sola vez.
            </StateMessage>
        </WorkerFrame>
    );
}
