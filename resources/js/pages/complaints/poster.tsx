import { Head, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import PosterSheet from '@/components/complaints/poster-sheet';
import Notice from '@/components/humetria/notice';
import PosterPage from '@/components/humetria/poster-page';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/complaints/channel';

const TIPS = [
    'Imprime en tamaño carta, al 100 %, sin ajustar a la página: así el QR se lee desde un metro.',
    'Un solo cartel sirve para todos tus centros: pega uno en cada centro, donde todas las personas lo vean sin tener que pedirlo.',
    'Quien escanea no necesita cuenta y puede contar lo que pasó sin dar su nombre.',
];

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
            <PosterPage
                tips={TIPS}
                controls={
                    <>
                        <ComplaintsHeader
                            eyebrow="Quejas y denuncias"
                            title="Cartel para imprimir"
                            lead="El mismo para todos tus centros, siempre en claro para imprimir bien en blanco y negro."
                        />
                        <Button onClick={() => window.print()}>
                            <Printer aria-hidden />
                            Imprimir
                        </Button>
                        {paused && (
                            <Notice>
                                <strong>El canal está en pausa.</strong> Quien
                                escanee este cartel verá que no está disponible
                                por ahora.
                            </Notice>
                        )}
                    </>
                }
            >
                <PosterSheet
                    company={company}
                    qr={qr}
                    shortUrl={shortUrl}
                    resolveWithinDays={resolveWithinDays}
                />
            </PosterPage>
        </>
    );
}
