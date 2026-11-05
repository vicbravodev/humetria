import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import WorkersShell from '@/components/disclosure/workers-shell';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { center, show } from '@/routes/workers';
import type { PublicCentersProps } from '@/types';

export default function PublicCenters({
    token,
    company,
    centers,
    filters,
}: PublicCentersProps) {
    const { text, type } = useQueryFilters(show.url(token), filters, ({ q }) =>
        q === '' ? {} : { q },
    );

    return (
        <WorkersShell company={company}>
            <Head title={company} />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        ¿En qué centro de trabajo está?
                    </h1>
                    <p className="text-muted-foreground">
                        Elija el suyo para ver lo que {company} le informa: su
                        política de prevención, las medidas, cómo presentar una
                        queja y los resultados.
                    </p>
                </div>
                <SearchField
                    label="Buscar su centro"
                    value={text}
                    onChange={type}
                />
                {centers.data.length === 0 ? (
                    <EmptyState icon={Building2} title="Ningún centro coincide">
                        Pruebe con otro nombre.
                    </EmptyState>
                ) : (
                    <ul className="flex flex-col divide-y overflow-hidden card">
                        {centers.data.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={center({
                                        token,
                                        workCenter: item.id,
                                    })}
                                    className="flex min-h-14 items-center gap-3 px-4 py-3 text-lg font-medium hover:bg-muted"
                                >
                                    <Building2
                                        aria-hidden
                                        className="size-5 shrink-0 text-primary"
                                    />
                                    {item.name}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination
                    page={centers}
                    label="Páginas de centros"
                    preserveState
                    pinned={false}
                />
            </div>
        </WorkersShell>
    );
}
