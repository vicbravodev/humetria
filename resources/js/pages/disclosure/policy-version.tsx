import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Calendar, ChevronLeft } from 'lucide-react';
import PolicySections from '@/components/disclosure/policy-sections';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import { countOf } from '@/lib/plural';
import { policy } from '@/routes/tenant/disclosure';
import type { PolicyVersion } from '@/types';

export default function PolicyVersionPage({
    version,
}: {
    version: PolicyVersion;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(
            tenantSlug,
            { title: 'Política de prevención', href: policy(tenantSlug) },
            { title: `Versión ${version.version}` },
        ),
    });

    return (
        <>
            <Head
                title={`Política de prevención · versión ${version.version}`}
            />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Política de prevención"
                    title={`Versión ${version.version}`}
                    facts={[
                        {
                            icon: Calendar,
                            text: `Publicada el ${version.publishedOn} en ${countOf(version.centers, 'centro', 'centros')}`,
                        },
                    ]}
                >
                    <Button asChild variant="outline">
                        <Link href={policy(tenantSlug)}>
                            <ChevronLeft aria-hidden />
                            Volver a la política
                        </Link>
                    </Button>
                </PageHeader>
                <div className="card p-5 md:p-6">
                    <PolicySections sections={version.sections} />
                </div>
            </div>
        </>
    );
}
