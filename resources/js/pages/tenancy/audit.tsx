import { Head, Link } from '@inertiajs/react';
import { Download, ScrollText } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import AuditTabs from '@/components/tenancy/audit-tabs';
import AuditToolbar from '@/components/tenancy/audit-toolbar';
import AuditWorkArea from '@/components/tenancy/audit-work-area';
import { Button } from '@/components/ui/button';
import { auditFiltered, auditQuery } from '@/lib/audit';
import { exportMethod, index } from '@/routes/audit';
import type {
    AuditCounts,
    AuditEntryRow,
    AuditFamily,
    AuditFilters,
    Paginated,
} from '@/types';

export default function Audit({
    entries,
    counts,
    filters,
    company,
    families,
}: {
    entries: Paginated<AuditEntryRow>;
    counts: AuditCounts;
    filters: AuditFilters;
    company: string | null;
    families: AuditFamily[];
}) {
    const filtered = auditFiltered(filters);

    return (
        <>
            <Head title="Bitácora" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Bitácora"
                    description="Todo lo sensible que pasó en la plataforma, de solo lectura y sin borrar. Nunca guarda respuestas de encuestas ni liga a una persona con una respuesta."
                >
                    {entries.total > 0 && (
                        <Button asChild variant="outline">
                            {/* A file, not a page: a plain download that also goes to the trail. */}
                            <a
                                href={exportMethod.url({
                                    query: auditQuery(filters),
                                })}
                            >
                                <Download aria-hidden />
                                Exportar CSV
                            </a>
                        </Button>
                    )}
                </PageHeader>
                <AuditToolbar
                    filters={filters}
                    families={families}
                    company={company}
                />
                <AuditTabs filters={filters} counts={counts} />
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
                    <AuditWorkArea entries={entries.data} families={families} />
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
