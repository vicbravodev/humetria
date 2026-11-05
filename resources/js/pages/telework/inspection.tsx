import { Head, setLayoutProps } from '@inertiajs/react';
import InspectionDossier from '@/components/telework/inspection-dossier';
import InspectionList from '@/components/telework/inspection-list';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dashboard } from '@/routes/tenant';
import { center } from '@/routes/tenant/telework';
import type { InspectionItem } from '@/types';

/** Board V3-Inspeccion: what an inspector would ask for today in a center, what is missing first, and its dossier. */
export default function TeleworkInspection({
    workCenter,
    applicable,
    missing,
    ready,
    dossierHref,
}: {
    workCenter: { id: string; name: string };
    applicable: number;
    missing: InspectionItem[];
    ready: InspectionItem[];
    dossierHref: string | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Panel', href: dashboard(tenantSlug) },
            {
                title: workCenter.name,
                href: center([tenantSlug, workCenter.id]),
            },
            { title: 'Inspección' },
        ],
    });

    return (
        <>
            <Head title={`Inspección · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-12 md:py-10">
                <header className="flex max-w-[760px] flex-col gap-2">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Si hoy llegara una inspección a {workCenter.name}
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        Esto es lo que te pediría el inspector, en sus palabras
                        y en las tuyas. Lo que tienes está listo en el
                        expediente; lo que falta tiene su botón para resolverlo.
                    </p>
                </header>
                <div className="grid gap-6 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <InspectionList
                            id="missing"
                            title="Te falta"
                            items={missing}
                            applicable={applicable}
                            empty="No te falta nada: puedes mostrar cada renglón."
                        />
                        <InspectionList
                            id="ready"
                            title="Lo puedes mostrar"
                            items={ready}
                            applicable={applicable}
                            empty="Todavía no tienes nada que mostrar. Empieza por lo que te falta."
                        />
                    </div>
                    {dossierHref !== null && (
                        <InspectionDossier href={dossierHref} />
                    )}
                </div>
            </div>
        </>
    );
}
