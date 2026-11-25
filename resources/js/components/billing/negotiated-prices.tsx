import { HandCoins } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { plansQuery } from '@/lib/billing-catalog';
import { index } from '@/routes/billing/plans';
import type { NegotiatedPriceRow, Paginated } from '@/types';

/** The prices agreed by hand that are in force, the one that ends first on top, searched by company. They replace the table price. */
export default function NegotiatedPrices({
    negotiated,
    filters,
}: {
    negotiated: Paginated<NegotiatedPriceRow>;
    filters: { q: string };
}) {
    const { text, type } = useQueryFilters(index.url(), filters, plansQuery);

    return (
        <section aria-labelledby="negotiated-prices" className="grid gap-4">
            <h2
                id="negotiated-prices"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Precios pactados vigentes
            </h2>
            <div role="search">
                <SearchField
                    label="Buscar por empresa"
                    value={text}
                    onChange={type}
                    className="w-full md:w-[380px]"
                />
            </div>
            {negotiated.data.length === 0 ? (
                <EmptyState
                    icon={HandCoins}
                    title={
                        filters.q === ''
                            ? 'Nadie tiene un precio pactado'
                            : 'Ninguna empresa coincide'
                    }
                >
                    Un precio pactado se pone en el plan de la empresa, desde la
                    cuenta que la paga.
                </EmptyState>
            ) : (
                <div className="overflow-hidden card p-0">
                    <Table>
                        <TableCaption className="sr-only">
                            Precios pactados vigentes, del que vence primero al
                            que no vence
                        </TableCaption>
                        <TableHeader>
                            <TableRow className="bg-muted hover:bg-muted">
                                <TableHead>Empresa</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead className="text-right">
                                    Al mes, sin IVA
                                </TableHead>
                                <TableHead>Vigente hasta</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {negotiated.data.map((row, position) => (
                                <TableRow
                                    // A company holds one plan: its name and the row's place never repeat on a page.
                                    key={`${row.company}-${position}`}
                                >
                                    <TableCell className="font-medium">
                                        {row.company}
                                    </TableCell>
                                    <TableCell>{row.plan}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {row.price}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.valid_until ?? 'Sin vencimiento'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
            <Pagination
                previous="Ver anteriores"
                next="Ver siguientes"
                page={negotiated}
                label="Páginas de precios pactados"
            />
        </section>
    );
}
