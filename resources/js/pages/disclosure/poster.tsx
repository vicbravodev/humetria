import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import DisclosureHeader from '@/components/disclosure/disclosure-header';
import PosterSheet from '@/components/disclosure/poster-sheet';
import RecordDialog from '@/components/disclosure/record-dialog';
import Combobox from '@/components/humetria/combobox';
import Notice from '@/components/humetria/notice';
import PosterPage from '@/components/humetria/poster-page';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import { serverSource } from '@/lib/server-options';
import { poster } from '@/routes/tenant/disclosure/page';
import { options } from '@/routes/tenant/work-centers';
import type { PosterProps } from '@/types';

const TIPS = [
    'Imprime en tamaño carta, al 100 %, sin ajustar a la página: así el QR se lee desde un metro.',
    'Pégalo a la altura de los ojos, junto al checador o en el comedor, donde todas las personas pasan.',
    'Al registrar dónde lo pegaste queda como medio de difusión del centro: dónde, cuándo y qué cubrió, con su foto.',
];

/** «Carteles para imprimir» (board V4-Carteles): the letter sheet of a center, and where it was hung, as a means of disclosure. */
export default function Poster({
    company,
    center,
    url,
    qr,
    active,
    channel,
    today,
}: PosterProps) {
    const tenantSlug = useTenantSlug();
    const [recording, setRecording] = useState(false);
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, { title: 'Cartel' }),
    });

    return (
        <>
            <Head title="Cartel para imprimir" />
            <PosterPage
                tips={TIPS}
                controls={
                    <>
                        <DisclosureHeader
                            eyebrow="Difusión"
                            title="Cartel para imprimir"
                            lead="Uno por centro, siempre en claro para imprimir bien en blanco y negro."
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="poster-center">El cartel de</Label>
                            <Combobox
                                id="poster-center"
                                name="center"
                                source={serverSource(options.url(tenantSlug))}
                                placeholder="Todos los centros"
                                defaultValue={
                                    center === null
                                        ? undefined
                                        : {
                                              value: center.id,
                                              label: center.name,
                                          }
                                }
                                onChange={(option) =>
                                    router.get(
                                        poster.url(tenantSlug, {
                                            query: { center: option.value },
                                        }),
                                    )
                                }
                            />
                            <p className="text-sm text-muted-foreground">
                                Sin centro elegido, el cartel sale sin nombre de
                                centro y su QR lleva a la lista de tus centros.
                                Al elegir uno, lleva su nombre y su QR abre
                                directo su página.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Button onClick={() => window.print()}>
                                <Printer aria-hidden />
                                {center === null
                                    ? 'Imprimir'
                                    : `Imprimir el de ${center.name}`}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setRecording(true)}
                            >
                                Registrar dónde lo pegaste
                            </Button>
                        </div>
                        {!active && (
                            <Notice>
                                <strong>La página no está activa.</strong> Quien
                                escanee este cartel verá que el enlace no abre.
                                Actívala antes de colgarlo.
                            </Notice>
                        )}
                    </>
                }
            >
                <PosterSheet
                    company={company}
                    center={center?.name ?? null}
                    url={url}
                    qr={qr}
                    channel={channel}
                />
            </PosterPage>
            {recording && (
                <RecordDialog
                    open={recording}
                    onOpenChange={setRecording}
                    today={today}
                    initialMedium="poster"
                />
            )}
        </>
    );
}
