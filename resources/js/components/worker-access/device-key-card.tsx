import { Smartphone } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import RecentProofContent from '@/components/worker-access/recent-proof-content';
import StatusLine from '@/components/worker-access/status-line';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { useDeviceKey } from '@/hooks/use-device-key';
import type { ProofStep } from '@/types';

const READY =
    'Este teléfono está listo para marcar sin internet. Para seguir usándolo, entre a Humetría con internet al menos una vez al día.';

/** What the person reads once preparing ended without being ready, or while it is. */
function outcomeLine(state: ReturnType<typeof useDeviceKey>['state']) {
    if (state === 'ready') {
        return <StatusLine text={READY} />;
    }

    if (state === 'session') {
        return <StatusLine problem text="Su sesión terminó. Entre de nuevo." />;
    }

    return state === 'failed' ? (
        <StatusLine
            problem
            text="No pudimos preparar este teléfono. Intente de nuevo."
        />
    ) : null;
}

/**
 * «Marcar sin internet con este teléfono» (plan B): when the person's key does not sign without a connection, this phone can
 * sign their marks. It is prepared here, with internet; the server asks for a recent proof first and the dialog gives it,
 * then it goes again by itself.
 */
export default function DeviceKeyCard({
    slug,
    optionsUrl,
    storeUrl,
    proof,
}: {
    slug: string;
    optionsUrl: string;
    storeUrl: string;
    proof: ProofStep;
}) {
    const key = useDeviceKey({ slug, optionsUrl, storeUrl });

    return (
        <section className="flex flex-col gap-3 card p-4">
            <div className="flex items-center gap-3.5">
                <IconTile icon={Smartphone} />
                <h2 className="text-base font-semibold">
                    Marcar sin internet con este teléfono
                </h2>
            </div>
            <p className="text-base text-muted-foreground">
                Si su llave no funciona sin internet, este teléfono puede firmar
                sus marcas. Prepárelo ahora, con internet. Puede preparar hasta
                3 teléfonos; al preparar otro se quita el que lleva más tiempo
                sin usarse.
            </p>
            {outcomeLine(key.state)}
            <Button
                size="lg"
                variant={key.state === 'ready' ? 'outline' : 'default'}
                className="w-full text-base"
                disabled={key.state === 'working'}
                onClick={() => void key.register()}
            >
                Preparar este teléfono
            </Button>
            <Dialog
                open={key.state === 'proof'}
                onOpenChange={(open) => {
                    if (!open) {
                        key.close();
                    }
                }}
            >
                {key.state === 'proof' && (
                    <RecentProofContent
                        step={proof}
                        onProved={() => void key.register()}
                    />
                )}
            </Dialog>
        </section>
    );
}
