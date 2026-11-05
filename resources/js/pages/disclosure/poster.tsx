import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import DisclosureHeader from '@/components/disclosure/disclosure-header';
import PosterSheet from '@/components/disclosure/poster-sheet';
import RecordDialog from '@/components/disclosure/record-dialog';
import Combobox from '@/components/humetria/combobox';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLightPrint } from '@/hooks/use-light-print';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import { serverSource } from '@/lib/server-options';
import { poster } from '@/routes/tenant/disclosure/page';
import { options } from '@/routes/tenant/work-centers';
import type { PosterProps } from '@/types';

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
    useLightPrint();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, { title: 'Cartel' }),
    });

    return (
        <>
            <Head title="Cartel para imprimir" />
            <div className="mx-auto flex w-full max-w-[1100px] flex-col gap-6 px-4 py-6 md:px-10 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-col gap-5 print:hidden">
                    <DisclosureHeader
                        eyebrow="Difusión"
                        title="Cartel para imprimir"
                        lead="Tamaño carta, uno por centro, siempre en claro para imprimir bien en blanco y negro. Pégalo junto al checador o en el comedor y regístralo como medio de difusión."
                    >
                        <div className="flex flex-wrap gap-3">
                            <Button
                                variant="outline"
                                onClick={() => setRecording(true)}
                            >
                                Registrar dónde lo pegaste
                            </Button>
                            <Button onClick={() => window.print()}>
                                <Printer aria-hidden />
                                {center === null
                                    ? 'Imprimir'
                                    : `Imprimir el de ${center.name}`}
                            </Button>
                        </div>
                    </DisclosureHeader>
                    <div className="grid max-w-[420px] gap-2">
                        <Label htmlFor="poster-center">El cartel de</Label>
                        <Combobox
                            id="poster-center"
                            name="center"
                            source={serverSource(options.url(tenantSlug))}
                            placeholder="Todos los centros: lleva a la lista"
                            defaultValue={
                                center === null
                                    ? undefined
                                    : { value: center.id, label: center.name }
                            }
                            onChange={(option) =>
                                router.get(
                                    poster.url(tenantSlug, {
                                        query: { center: option.value },
                                    }),
                                )
                            }
                        />
                    </div>
                    {!active && (
                        <Notice>
                            <strong>La página no está activa.</strong> Quien
                            escanee este cartel verá que el enlace no abre.
                            Actívala antes de colgarlo.
                        </Notice>
                    )}
                </div>
                <PosterSheet
                    company={company}
                    center={center?.name ?? null}
                    url={url}
                    qr={qr}
                    channel={channel}
                />
            </div>
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
