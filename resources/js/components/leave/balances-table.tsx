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
    today,
    selection,
    canManage,
}: {
    people: LeaveBalanceRow[];
    today: string;
    selection: IdSelection;
    canManage: boolean;
}) {
    return (
        <div className="overflow-hidden card p-0">
            <StackedTable>
                <TableCaption className="sr-only">
                    Días de vacaciones de cada persona
                </TableCaption>
                <TableHeader>
                    <TableRow className="bg-muted hover:bg-muted">
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
                            <span className="sr-only">Más acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.map((person) => (
                        <BalanceRow
                            key={person.id}
                            person={person}
                            today={today}
                            selectable={canManage}
                            chosen={selection.isChosen(person.id)}
                            onChoose={(checked) =>
                                selection.toggle(person.id, checked)
                            }
                        />
                    ))}
                </TableBody>
            </StackedTable>
        </div>
    );
}
