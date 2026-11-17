import { router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import StateMessage from '@/components/worker-access/state-message';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** «No fui yo» (CUJ 6): what confirming does, before it does it. */
export default function NotMeConfirm({
    company,
    label,
    confirmUrl,
}: {
    company: string;
    label: string;
    confirmUrl: string;
}) {
    const [working, setWorking] = useState(false);
    const confirm = () =>
        router.post(
            confirmUrl,
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
                    variant="destructive"
                    className="h-14 w-full text-lg font-semibold"
                    disabled={working}
                    onClick={confirm}
                >
                    {working && <Spinner className="size-6" />}
                    Anular esa llave
                </Button>
            }
        >
            <StateMessage
                icon={ShieldAlert}
                tone="warning"
                title={`¿No creó usted la llave «${label}»?`}
            >
                Al anularla se cierran todas sus sesiones y avisamos a Recursos
                Humanos. Para volver a entrar, pídales un enlace nuevo.
            </StateMessage>
        </WorkerFrame>
    );
}
