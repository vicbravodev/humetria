import BalanceRow from '@/components/leave/balance-row';
import StackedTable from '@/components/humetria/stacked-table';
import ChooseBox from '@/components/humetria/choose-box';
import {
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { IdSelection } from '@/hooks/use-id-selection';
import type { LeaveBalanceRow } from '@/types';

/** The people of a tab with their days; the ticks are only for whoever may send links. */
export default function BalancesTable({
    people,
    selection,
    canManage,
}: {
    people: LeaveBalanceRow[];
    selection: IdSelection;
    canManage: boolean;
}) {
    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Días de vacaciones de cada persona
            </TableCaption>
            <TableHeader>
                <TableRow>
                    {canManage && (
                        <TableHead className="w-10">
                            <ChooseBox
                                checked={selection.allChosen}
                                onChange={selection.toggleAll}
                                name="Elegir a todas las personas de esta página"
                            />
                        </TableHead>
                    )}
                    <TableHead>Persona</TableHead>
                    <TableHead>Disponibles</TableHead>
                    <TableHead>Próximo periodo</TableHead>
                    <TableHead className="w-0">
                        <span className="sr-only">Expediente</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <BalanceRow
                        key={person.id}
                        person={person}
                        selectable={canManage}
                        chosen={selection.isChosen(person.id)}
                        onChoose={(checked) =>
                            selection.toggle(person.id, checked)
                        }
                    />
                ))}
            </TableBody>
        </StackedTable>
    );
}
