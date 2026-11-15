import { Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { balancesQuery } from '@/lib/leave/balances';
import { countOf } from '@/lib/plural';
import { index } from '@/routes/tenant/vacations/balances';
import type { LeaveBalanceFilters, LeaveBalanceTab } from '@/types';

const EMPTY: Record<LeaveBalanceTab, string> = {
    all: 'Da de alta a las personas en Empleados; aquí verás sus días de vacaciones.',
    missing:
        'Todas las personas tienen su fecha de ingreso: ya se les otorgan sus periodos.',
    statements:
        'Nadie tiene una constancia pendiente de confirmar. Cada aniversario genera la suya.',
    terminations: 'No hay bajas con días por finiquitar.',
};

/** A list of balances with nobody: nothing yet, nobody with that name, or somebody with that name in another tab (then it offers «Todos»). */
export default function BalancesEmpty({
    filters,
    elsewhere,
}: {
    filters: LeaveBalanceFilters;
    elsewhere: number;
}) {
    const tenantSlug = useTenantSlug();

    if (filters.q === '') {
        return (
            <EmptyState icon={Users} title="Nadie por aquí">
                {EMPTY[filters.tab]}
            </EmptyState>
        );
    }

    if (elsewhere === 0) {
        return (
            <EmptyState icon={Users} title="Nadie con ese nombre">
                Revisa cómo lo escribiste o busca con otra parte del nombre.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Users}
            title="No está en esta pestaña"
            action={
                <Button asChild>
                    <Link
                        href={index.url(tenantSlug, {
                            query: balancesQuery({ ...filters, tab: 'all' }),
                        })}
                    >
                        Ver en Todos
                    </Link>
                </Button>
            }
        >
            {`Nadie de esta pestaña se llama así, pero ${countOf(elsewhere, 'persona coincide', 'personas coinciden')} en Todos.`}
        </EmptyState>
    );
}
