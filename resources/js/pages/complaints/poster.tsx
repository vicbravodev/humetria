import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import PosterSheet from '@/components/complaints/poster-sheet';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/complaints/channel';

export default function Poster({
    company,
    shortUrl,
    qr,
    procedure,
    paused,
}: {
    company: string;
    url: string;
    shortUrl: string;
    qr: string;
    procedure: string;
    paused: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title="Cartel del canal de quejas" />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link href={show.url(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Volver al canal
                        </Link>
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer aria-hidden />
                        Imprimir o guardar PDF
                    </Button>
                </div>
                {paused && (
                    <div className="print:hidden">
                        <Notice>
                            <strong>El canal está en pausa.</strong> Quien
                            escanee este cartel verá que no está disponible por
                            ahora.
                        </Notice>
                    </div>
                )}
                <PosterSheet
                    company={company}
                    qr={qr}
                    shortUrl={shortUrl}
                    procedure={procedure}
                />
            </div>
        </>
    );
}

Poster.layout = {
    breadcrumbs: [
        { title: 'NOM-035' },
        { title: 'Quejas y denuncias' },
        { title: 'Cartel' },
    ],
};
