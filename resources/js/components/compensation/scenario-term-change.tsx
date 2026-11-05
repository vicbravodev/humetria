import { Trash2 } from 'lucide-react';
import TenureTableField from '@/components/compensation/tenure-table-field';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import type { TermChangeForm } from '@/lib/compensation/scenarios';

/** One benefit the scenario changes: its new value in its unit and, if it grows with seniority, its new table. */
export default function ScenarioTermChange({
    change,
    index,
    onValue,
    onRemove,
}: {
    change: TermChangeForm;
    index: number;
    onValue: (value: string) => void;
    onRemove: () => void;
}) {
    const field = `term_changes[${index}]`;

    return (
        <li className="grid gap-4 rounded-lg border bg-muted/40 p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold">{change.name}</p>
                    <p className="text-sm text-muted-foreground">
                        {change.current_text === null
                            ? 'No está en el contrato de hoy: se agrega con esta propuesta.'
                            : `Hoy: ${change.current_text}.`}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Quitar ${change.name}`}
                    onClick={onRemove}
                >
                    <Trash2 />
                </Button>
            </div>
            <input
                type="hidden"
                name={`${field}[benefit_id]`}
                value={change.benefit_id}
            />
            <FormField
                name={`${field}[value]`}
                idPrefix="scenario"
                label={`Valor nuevo (${change.unit})`}
                inputMode="decimal"
                autoComplete="off"
                value={change.value}
                onChange={(event) => onValue(event.target.value)}
            />
            <TenureTableField
                initial={change.tenure_table}
                unit={change.unit}
                error={undefined}
                namePrefix={`${field}[tenure_table]`}
            />
        </li>
    );
}
