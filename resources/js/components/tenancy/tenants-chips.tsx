import { router } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { companiesQuery } from '@/lib/tenant-status';
import { index } from '@/routes/companies';
import type { StaffMember, TenantFilters } from '@/types';

const ANY = 'any';

/** The chips of the companies list (tablero Empresas): plan, who answers for them and seats almost used up, on the server. */
export default function TenantsChips({
    filters,
    plans,
    staff,
}: {
    filters: TenantFilters;
    plans: { value: string; label: string }[];
    staff: StaffMember[];
}) {
    const go = (change: Partial<TenantFilters>) =>
        router.get(
            index.url({ query: companiesQuery({ ...filters, ...change }) }),
            {},
            { preserveScroll: true, replace: true },
        );
    const pick = (key: 'plan' | 'manager') => (value: string) =>
        go({ [key]: value === ANY ? '' : value });
    const high = filters.usage === 'high';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select value={filters.plan || ANY} onValueChange={pick('plan')}>
                <SelectTrigger aria-label="Plan" className="w-auto min-w-36">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>Plan: todos</SelectItem>
                    {plans.map((plan) => (
                        <SelectItem key={plan.value} value={plan.value}>
                            {plan.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={filters.manager || ANY}
                onValueChange={pick('manager')}
            >
                <SelectTrigger
                    aria-label="Responsable"
                    className="w-auto min-w-40"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>Responsable: todos</SelectItem>
                    <SelectItem value="none">Sin asignar</SelectItem>
                    {staff.map((person) => (
                        <SelectItem key={person.id} value={String(person.id)}>
                            {person.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button
                variant={high ? 'secondary' : 'outline'}
                aria-pressed={high}
                data-test="high-usage"
                onClick={() => go({ usage: high ? '' : 'high' })}
            >
                <Gauge aria-hidden />
                Uso &gt; 80 %
            </Button>
        </div>
    );
}
