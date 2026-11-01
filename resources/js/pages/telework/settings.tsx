import { Head, usePage } from '@inertiajs/react';
import PageHeader from '@/components/page-header';
import SettingsForm from '@/components/telework/settings-form';

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
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title="Ajustes de NOM-037" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Ajustes de NOM-037"
                    description="Cada cuánto se revisan los lugares de teletrabajo y qué consentimiento aceptan las personas."
                />
                <div className="rounded-xl border bg-card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    <SettingsForm
                        tenantSlug={tenantSlug}
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

Settings.layout = {
    breadcrumbs: [{ title: 'NOM-037' }, { title: 'Ajustes' }],
};
