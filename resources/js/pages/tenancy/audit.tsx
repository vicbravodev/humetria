import { Head, Link } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import AuditTable from '@/components/tenancy/audit-table';
import AuditToolbar from '@/components/tenancy/audit-toolbar';
import { Button } from '@/components/ui/button';
import { auditFiltered } from '@/lib/audit';
import { countOf } from '@/lib/plural';
import { index } from '@/routes/audit';
import type {
    AuditEntryRow,
    AuditFamily,
    AuditFilters,
    Paginated,
} from '@/types';

export default function Audit({
    entries,
    filters,
    company,
    families,
}: {
    entries: Paginated<AuditEntryRow>;
    filters: AuditFilters;
    company: string | null;
    families: AuditFamily[];
}) {
    const filtered = auditFiltered(filters);

    return (
        <>
            <Head title="Bitácora" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Bitácora"
                    description={`${countOf(entries.total, 'registro', 'registros')}, el más reciente primero. Solo se lee: nada de aquí se edita ni se borra.`}
                />
                <AuditToolbar
                    filters={filters}
                    families={families}
                    company={company}
                />
                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title={
                            filtered
                                ? 'Nada coincide con estos filtros'
                                : 'Aún no hay registros'
                        }
                        action={
                            filtered ? (
                                <Button asChild variant="outline">
                                    <Link href={index()}>Quitar filtros</Link>
                                </Button>
                            ) : undefined
                        }
                    >
                        {filtered
                            ? 'Prueba con otro tipo de evento, otras fechas o otro correo.'
                            : 'Cada acción sensible de la plataforma quedará aquí con quién la hizo y cuándo.'}
                    </EmptyState>
                ) : (
                    <AuditTable entries={entries.data} />
                )}
                <Pagination
                    previous="Ver más recientes"
                    next="Ver más antiguos"
                    page={entries}
                    label="Páginas de la bitácora"
                />
            </div>
        </>
    );
}
