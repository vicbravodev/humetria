import { Head, setLayoutProps } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import SettingsForm from '@/components/telework/settings-form';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { teleworkCrumbs } from '@/lib/telework/crumbs';

export default function Settings({
    periodMonths,
    presets,
    maxMonths,
    consent,
}: {
    periodMonths: number;
    presets: number[];
    maxMonths: number;
    consent: { version: number; text: string };
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(tenantSlug, null, { title: 'Ajustes' }),
    });
    return (
        <>
            <Head title="Ajustes de NOM-037" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Ajustes de NOM-037"
                    description="Cada cuánto se revisan los lugares de teletrabajo y qué consentimiento aceptan las personas."
                />
                <div className="card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    <SettingsForm
                        periodMonths={periodMonths}
                        presets={presets}
                        maxMonths={maxMonths}
                        consent={consent}
                    />
                </div>
            </div>
        </>
    );
}
