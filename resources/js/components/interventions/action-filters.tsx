import { router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { statusChoices } from '@/lib/action-form';
import type { ActionFilters, ProgramRow } from '@/types';

const ALL = 'all';

/** Narrows the actions by row and by state: a visit to the same page with the new query, so the server pages the result. */
export default function ActionFilters({
    url,
    rows,
    filters,
}: {
    url: string;
    rows: ProgramRow[];
    filters: ActionFilters;
}) {
    const visit = (next: Partial<ActionFilters>) => {
        const query = { ...filters, ...next };

        router.get(
            url,
            {
                tab: 'actions',
                ...(query.item === null ? {} : { item: query.item }),
                ...(query.status === null ? {} : { status: query.status }),
            },
            { preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="filter-item">Fila</Label>
                <Select
                    value={filters.item ?? ALL}
                    onValueChange={(value) =>
                        visit({ item: value === ALL ? null : value })
                    }
                >
                    <SelectTrigger id="filter-item" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Todas las filas</SelectItem>
                        {rows.map((row) => (
                            <SelectItem key={row.id} value={row.id}>
                                {row.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-status">Estado</Label>
                <Select
                    value={filters.status ?? ALL}
                    onValueChange={(value) =>
                        visit({ status: value === ALL ? null : value })
                    }
                >
                    <SelectTrigger id="filter-status" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Todos los estados</SelectItem>
                        {statusChoices().map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
