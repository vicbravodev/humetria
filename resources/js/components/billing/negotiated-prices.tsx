import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { Badge } from '@/components/ui/badge';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { plansQuery } from '@/lib/billing-catalog';
import { index } from '@/routes/billing/plans';
import type { NegotiatedPriceRow, Paginated } from '@/types';

/**
 * «Precios especiales vigentes» (tablero Planes y precios): the prices agreed by hand that are in force, the one that ends
 * first on top, searched by company. They replace the table price.
 */
export default function NegotiatedPrices({
    negotiated,
    filters,
}: {
    negotiated: Paginated<NegotiatedPriceRow>;
    filters: { q: string };
}) {
    const { text, type } = useQueryFilters(index.url(), filters, plansQuery);
    const searching = filters.q !== '';

    return (
        <section aria-labelledby="negotiated-prices" className="card p-0">
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2 id="negotiated-prices" className="flex-1 font-semibold">
                    Precios especiales vigentes
                </h2>
                <Badge>{negotiated.total}</Badge>
            </div>
            {(searching || negotiated.total > 0) && (
                <div role="search" className="border-b px-6 py-3">
                    <SearchField
                        label="Buscar por empresa"
                        value={text}
                        onChange={type}
                    />
                </div>
            )}
            {negotiated.data.length === 0 ? (
                <div className="grid gap-1 px-6 py-4">
                    <p className="font-medium">
                        {searching
                            ? 'Ninguna empresa coincide'
                            : 'Nadie tiene un precio pactado'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Un precio pactado se pone en el plan de la empresa,
                        desde la cuenta que la paga.
                    </p>
                </div>
            ) : (
                <ul className="divide-y">
                    {negotiated.data.map((row, position) => (
                        <li
                            // A company holds one plan: its name and the row's place never repeat on a page.
                            key={`${row.company}-${position}`}
                            className="flex items-center gap-3 px-6 py-3.5"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{row.company}</p>
                                <p className="text-sm text-muted-foreground">
                                    {row.plan} ·{' '}
                                    {row.valid_until === null
                                        ? 'sin vencimiento'
                                        : `vence ${row.valid_until}`}
                                </p>
                            </div>
                            <Badge variant="warning">{row.price}</Badge>
                        </li>
                    ))}
                </ul>
            )}
            <div className="border-t px-6 py-3 empty:hidden">
                <Pagination
                    previous="Anterior"
                    next="Siguiente"
                    page={negotiated}
                    label="Páginas de precios pactados"
                    pinned={false}
                />
            </div>
        </section>
    );
}
