import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CertificateSheet from '@/components/humetria/certificate-sheet';
import type { CertificateCenter } from '@/components/humetria/certificate-sheet';
import PrintButton from '@/components/humetria/print-button';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dashboard } from '@/routes/tenant';
import { index } from '@/routes/tenant/telework';

type Props = {
    workCenter: CertificateCenter & { id: string };
    /** "3 de febrero de 2026". */
    declaredOn: string;
    declaredBy: string | null;
};

/** The certificate that NOM-037 does not apply to a center, to print (decisiones A2 y D6). */
export default function TeleworkCertificate(props: Props) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037', href: index(tenantSlug) },
            { title: props.workCenter.name },
            { title: 'Constancia de que no aplica' },
        ],
    });

    return (
        <>
            <Head title={`Constancia NOM-037 · ${props.workCenter.name}`} />
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
                    standard="NOM-037-STPS-2023 · Teletrabajo"
                    center={props.workCenter}
                    declaredOn={props.declaredOn}
                    declaredBy={props.declaredBy}
                >
                    Se declaró que en este centro de trabajo nadie labora fuera
                    de sus instalaciones más del 40 % de su tiempo. Por eso no
                    tiene personas en teletrabajo en los términos del artículo
                    330-A de la Ley Federal del Trabajo, y la NOM-037-STPS-2023
                    no le aplica mientras eso no cambie.
                </CertificateSheet>
            </div>
        </>
    );
}
