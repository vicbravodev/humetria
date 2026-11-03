import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import PosterSheet from '@/components/disclosure/poster-sheet';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useLightPrint } from '@/hooks/use-light-print';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { page } from '@/routes/tenant/disclosure';
import type { PosterProps } from '@/types';

export default function Poster({ company, url, qr, active }: PosterProps) {
    const tenantSlug = useTenantSlug();
    useLightPrint();

    return (
        <>
            <Head title="Cartel de la página de las personas" />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link href={page.url(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Volver a la página
                        </Link>
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer aria-hidden />
                        Imprimir o guardar PDF
                    </Button>
                </div>
                {!active && (
                    <div className="print:hidden">
                        <Notice>
                            <strong>La página no está activa.</strong> Quien
                            escanee este cartel verá que el enlace no abre.
                            Actívala antes de colgarlo.
                        </Notice>
                    </div>
                )}
                <PosterSheet company={company} url={url} qr={qr} />
            </div>
        </>
    );
}

Poster.layout = {
    breadcrumbs: [
        { title: 'NOM-035' },
        { title: 'Política y difusión' },
        { title: 'Cartel' },
    ],
};
