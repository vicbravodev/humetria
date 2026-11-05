import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CertificateSheet from '@/components/humetria/certificate-sheet';
import type { CertificateCenter } from '@/components/humetria/certificate-sheet';
import PrintButton from '@/components/humetria/print-button';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dashboard } from '@/routes/tenant';
import { index } from '@/routes/tenant/seating';

type Props = {
    workCenter: CertificateCenter & { id: string };
    /** "3 de febrero de 2026". */
    declaredOn: string;
    declaredBy: string | null;
};

/** The certificate that Ley Silla does not apply to a center, to print (decisiones A2 y D6). */
export default function SeatingCertificate(props: Props) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            { title: props.workCenter.name },
            { title: 'Constancia de que no aplica' },
        ],
    });

    return (
        <>
            <Head title={`Constancia Ley Silla · ${props.workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link href={dashboard(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Volver al panel
                        </Link>
                    </Button>
                    <PrintButton />
                </div>
                {/* TODO-LEGAL: la redacción de la constancia la valida un abogado antes de producción. */}
                <CertificateSheet
                    standard="Ley Silla · trabajo de pie"
                    center={props.workCenter}
                    declaredOn={props.declaredOn}
                    declaredBy={props.declaredBy}
                >
                    Se declaró que en este centro de trabajo nadie trabaja de
                    pie la mayor parte de su jornada. Por eso no tiene puestos
                    que requieran los asientos que pide el artículo 132,
                    fracción V, de la Ley Federal del Trabajo mientras eso no
                    cambie.
                </CertificateSheet>
            </div>
        </>
    );
}
