import { FileText } from 'lucide-react';
import Pagination from '@/components/humetria/pagination';
import EmptyState from '@/components/humetria/empty-state';
import NoticeRow from '@/components/telework/notice-row';
import type { NoticeRow as Row, Paginated } from '@/types';

/** The notices, the ones nobody reviewed first. */
export default function NoticeList({ notices }: { notices: Paginated<Row> }) {
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
                        <NoticeRow key={row.id} row={row} />
                    ))}
                </ol>
            )}
            <Pagination
                page={notices}
                label="Páginas de avisos"
                pinned={false}
            />
        </section>
    );
}
