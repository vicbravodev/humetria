import { FileText } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import NoticeRow from '@/components/telework/notice-row';
import type { NoticeRow as Row, Paginated } from '@/types';

/** The notices, the ones nobody reviewed first. */
export default function NoticeList({
    tenantSlug,
    notices,
}: {
    tenantSlug: string;
    notices: Paginated<Row>;
}) {
    return (
        <section className="grid gap-3" aria-labelledby="notices-title">
            <h2 id="notices-title" className="text-xl font-semibold">
                Avisos recibidos
            </h2>
            {notices.total === 0 ? (
                <EmptyState icon={FileText} title="Aún no hay avisos">
                    Cuando una persona avise de un cambio de domicilio, un
                    riesgo de trabajo o una alteración de sus condiciones, lo
                    verás aquí.
                </EmptyState>
            ) : (
                <ol aria-label="Avisos" className="flex flex-col gap-3">
                    {notices.data.map((row) => (
                        <NoticeRow
                            key={row.id}
                            tenantSlug={tenantSlug}
                            row={row}
                        />
                    ))}
                </ol>
            )}
            <ConsolePagination
                page={notices}
                label="Páginas de avisos"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
