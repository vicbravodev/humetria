import { Head, setLayoutProps } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import BalancesTable from '@/components/leave/balances-table';
import BalancesToolbar from '@/components/leave/balances-toolbar';
import SendLinksBar from '@/components/leave/send-links-bar';
import { useIdSelection } from '@/hooks/use-id-selection';
import type {
    LeaveBalanceFilters,
    LeaveBalanceRow,
    LeaveBalanceTab,
    Paginated,
} from '@/types';

const EMPTY: Record<LeaveBalanceTab, string> = {
    all: 'Da de alta a las personas en Empleados; aquí verás sus días de vacaciones.',
    missing:
        'Todas las personas tienen su fecha de ingreso: ya se les otorgan sus periodos.',
    statements:
        'Nadie tiene una constancia pendiente de confirmar. Cada aniversario genera la suya.',
    terminations: 'No hay bajas con días por finiquitar.',
};

/**
 * The days of everybody: their policy, what they can still ask for and when the next period starts, with a tab for each
 * thing RH has to chase. Days only, never an amount.
 */
export default function LeaveBalances({
    tab,
    q,
    counts,
    canManage,
    people,
}: LeaveBalanceFilters & {
    counts: Record<LeaveBalanceTab, number>;
    canManage: boolean;
    people: Paginated<LeaveBalanceRow>;
}) {
    const selection = useIdSelection(people.data.map((person) => person.id));

    setLayoutProps({
        breadcrumbs: [{ title: 'Vacaciones' }, { title: 'Saldos' }],
    });

    return (
        <>
            <Head title="Saldos de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Saldos"
                    description="Los días que cada persona puede pedir, y lo que falta para que el sistema pueda otorgárselos."
                />
                <BalancesToolbar filters={{ q, tab }} counts={counts} />
                {canManage && selection.chosen.length > 0 && (
                    <SendLinksBar
                        chosen={selection.chosen}
                        onDone={selection.clear}
                    />
                )}
                {people.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            q === '' ? 'Nadie por aquí' : 'Nadie con ese nombre'
                        }
                    >
                        {q === ''
                            ? EMPTY[tab]
                            : 'Revisa cómo lo escribiste o busca con otra parte del nombre.'}
                    </EmptyState>
                ) : (
                    <BalancesTable
                        people={people.data}
                        selection={selection}
                        canManage={canManage}
                    />
                )}
                <Pagination page={people} label="Páginas de personas" />
            </div>
        </>
    );
}
