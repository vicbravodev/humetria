import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ClipboardList } from 'lucide-react';
import Nom035DossierRow from '@/components/tenancy/nom035-dossier-row';
import EmptyState from '@/components/humetria/empty-state';
import PrintLetterhead from '@/components/humetria/print-letterhead';
import PrintButton from '@/components/humetria/print-button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dossierNote } from '@/lib/nom035/dossier';
import { nom035Crumbs } from '@/lib/nom035/crumbs';
import { inspection } from '@/routes/tenant/nom035';
import type { Nom035DossierProps } from '@/types';

/** The printable NOM-035 dossier of a center: one section per row of the inspection table, with its evidence. */
export default function Nom035Dossier({
    center,
    issuedOn,
    readyOnly,
    rows,
}: Nom035DossierProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: nom035Crumbs(tenantSlug, center, { title: 'Expediente' }),
    });

    return (
        <>
            <Head title={`Expediente NOM-035 · ${center.name}`} />
            <div className="mx-auto flex w-full max-w-[960px] flex-col gap-6 px-4 py-6 [page:dossier] md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Link
                        href={inspection([tenantSlug, center.id])}
                        className="flex min-h-11 w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft aria-hidden="true" className="size-[18px]" />
                        Si hoy llegara una inspección
                    </Link>
                    <PrintButton />
                </div>
                <PrintLetterhead center={center.name} issued={issuedOn} />
                <header className="flex flex-col gap-1">
                    <p className="text-meta text-muted-foreground">
                        Expediente NOM-035 para la inspección
                    </p>
                    <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em] text-balance">
                        {center.name}
                    </h1>
                    {center.address !== '' && (
                        <p className="text-meta">{center.address}</p>
                    )}
                    <p className="text-meta text-muted-foreground">
                        {dossierNote(issuedOn, readyOnly)}
                    </p>
                </header>
                {rows.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Todavía no hay nada que mostrar"
                    >
                        Empieza por lo que falta en la ruta del centro y vuelve
                        a abrir el expediente.
                    </EmptyState>
                ) : (
                    rows.map((row) => (
                        <Nom035DossierRow key={row.numeral} row={row} />
                    ))
                )}
                <p className="text-meta text-muted-foreground">
                    Es un orden de lo registrado en Humetría, no una constancia
                    de cumplimiento ante la autoridad.
                </p>
            </div>
        </>
    );
}
