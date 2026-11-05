import { Head, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import PosterSheet from '@/components/complaints/poster-sheet';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/complaints/channel';

/** «Carteles para imprimir», el de quejas (board V4-Carteles): one letter sheet for all the centers, printed with CSS. */
export default function Poster({
    company,
    shortUrl,
    qr,
    resolveWithinDays,
    paused,
}: {
    company: string;
    url: string;
    shortUrl: string;
    qr: string;
    resolveWithinDays: number;
    paused: boolean;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Quejas' },
            { title: 'Canal', href: show(tenantSlug) },
            { title: 'Cartel' },
        ],
    });

    return (
        <>
            <Head title="Cartel del canal de quejas" />
            <div className="mx-auto flex w-full max-w-[1100px] flex-col gap-6 px-4 py-6 md:px-10 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-col gap-5 print:hidden">
                    <ComplaintsHeader
                        eyebrow="Quejas y denuncias"
                        title="Cartel para imprimir"
                        lead="Tamaño carta, siempre en claro para imprimir bien en blanco y negro. El mismo cartel sirve en todos tus centros: pégalo donde todas las personas lo vean."
                    >
                        <Button onClick={() => window.print()}>
                            <Printer aria-hidden />
                            Imprimir
                        </Button>
                    </ComplaintsHeader>
                    {paused && (
                        <Notice>
                            <strong>El canal está en pausa.</strong> Quien
                            escanee este cartel verá que no está disponible por
                            ahora.
                        </Notice>
                    )}
                </div>
                <PosterSheet
                    company={company}
                    qr={qr}
                    shortUrl={shortUrl}
                    resolveWithinDays={resolveWithinDays}
                />
            </div>
        </>
    );
}
