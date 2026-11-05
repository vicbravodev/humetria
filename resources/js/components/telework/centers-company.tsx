import { Link } from '@inertiajs/react';
import { FilePlus2, FileText, GraduationCap } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as documents } from '@/routes/tenant/telework/documents';
import { index as trainings } from '@/routes/tenant/telework/trainings';
import type { TeleworkCompany } from '@/types';

/** «Para toda la empresa» (board V2-Centros-N037): the policy, its supporting documents and the yearly training. */
export default function CentersCompany({
    company,
}: {
    company: TeleworkCompany;
}) {
    const tenantSlug = useTenantSlug();
    const policyDone =
        company.centers > 0 && company.policyCenters === company.centers;
    const rows = [
        {
            icon: FileText,
            title: 'Política de Teletrabajo',
            text: `Vigente en ${company.policyCenters} de ${company.centers} centros · numeral 5.2`,
            href: documents(tenantSlug),
            action: policyDone ? 'Ver' : 'Continuar',
            primary: !policyDone,
        },
        {
            icon: FilePlus2,
            title: '6 documentos de apoyo',
            text: `${company.documentsReady} de 6 vigentes en todos tus centros · 5.3, 5.6, 5.8, 5.10, 5.13 y 5.14`,
            href: documents(tenantSlug),
            action: 'Ver documentos',
            primary: false,
        },
        {
            icon: GraduationCap,
            title: 'Capacitación anual',
            text: 'Cada persona, una vez al año · numeral 5.9',
            href: trainings(tenantSlug),
            action: 'Ver capacitación',
            primary: false,
        },
    ];

    return (
        <section aria-labelledby="company" className="flex flex-col gap-3">
            <div>
                <h2 id="company" className="text-xl font-semibold">
                    Para toda la empresa
                </h2>
                <p className="text-sm text-muted-foreground">
                    Se hace una vez y cubre a tus{' '}
                    {company.centers === 1
                        ? 'centro'
                        : `${company.centers} centros`}
                    .
                </p>
            </div>
            <ul className="@container overflow-hidden card">
                {rows.map((row) => (
                    <li
                        key={row.title}
                        className="grid grid-cols-[40px_minmax(0,1fr)] items-center gap-x-4 gap-y-2.5 border-t px-5 py-4 first:border-t-0 @[560px]:grid-cols-[40px_minmax(0,1fr)_auto]"
                    >
                        <IconTile icon={row.icon} />
                        <div className="min-w-0">
                            <p className="font-medium">{row.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {row.text}
                            </p>
                        </div>
                        <Button
                            asChild
                            variant={row.primary ? 'default' : 'outline'}
                            className="col-start-2 justify-self-start @[560px]:col-start-auto"
                        >
                            <Link href={row.href}>{row.action}</Link>
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
