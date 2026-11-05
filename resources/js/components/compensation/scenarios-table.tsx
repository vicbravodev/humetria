import ScenarioTableRow from '@/components/compensation/scenario-table-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ScenarioRow } from '@/types';

/** The scenarios, the newest first: what each proposes and what it costs in its first year, with the months of retroactivity in it. */
export default function ScenariosTable({
    scenarios,
    chosen,
    full,
    onChoose,
}: {
    scenarios: ScenarioRow[];
    chosen: string[];
    /** No more can be ticked: the comparison holds four. */
    full: boolean;
    onChoose: (id: string, checked: boolean) => void;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Escenarios, los más recientes primero
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-px">
                        <span className="sr-only">Comparar</span>
                    </TableHead>
                    <TableHead>Escenario</TableHead>
                    <TableHead>Aumento al salario</TableHead>
                    <TableHead className="text-right">
                        Costo del primer año
                    </TableHead>
                    <TableHead className="text-right">
                        Costo anual recurrente
                    </TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {scenarios.map((scenario) => (
                    <ScenarioTableRow
                        key={scenario.id}
                        scenario={scenario}
                        checked={chosen.includes(scenario.id)}
                        disabled={full && !chosen.includes(scenario.id)}
                        onChoose={onChoose}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
