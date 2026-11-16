import { router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import WorkerFrame from '@/components/worker-access/worker-frame';
import ToneTile from '@/components/humetria/tone-tile';
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
            <section className="flex flex-col items-start gap-3">
                <ToneTile icon={ShieldAlert} tone="warning" large />
                <h1 className="text-2xl leading-8 font-semibold text-balance">
                    ¿No creó usted la llave «{label}»?
                </h1>
                <p className="text-muted-foreground">
                    Al anularla se cierran todas sus sesiones y avisamos a
                    Recursos Humanos. Para volver a entrar, pídales un enlace
                    nuevo.
                </p>
            </section>
        </WorkerFrame>
    );
}
