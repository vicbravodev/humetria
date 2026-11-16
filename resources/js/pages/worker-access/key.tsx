import { Head } from '@inertiajs/react';
import KeyBlocked from '@/components/worker-access/key-blocked';
import KeyScreen from '@/components/worker-access/key-screen';
import { useWorkerKey } from '@/hooks/use-worker-key';
import { deviceLabel } from '@/lib/webauthn';

/** The person creates a key on this device; or why this session may not. */
export default function Key({
    company,
    name,
    blocked,
    optionsUrl,
    storeUrl,
    homeUrl,
}: {
    company: string;
    name: string;
    blocked: 'forbidden' | 'full' | null;
    optionsUrl: string;
    storeUrl: string;
    homeUrl: string;
}) {
    const key = useWorkerKey({
        optionsUrl,
        storeUrl,
        label: deviceLabel(navigator.userAgent),
    });

    return (
        <>
            <Head title="Crear mi llave" />
            {blocked === null ? (
                <KeyScreen
                    company={company}
                    name={name}
                    working={key.working}
                    failure={key.failure}
                    onCreate={() => void key.register()}
                />
            ) : (
                <KeyBlocked
                    company={company}
                    reason={blocked}
                    homeUrl={homeUrl}
                />
            )}
        </>
    );
}
