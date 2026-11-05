import { Trash2 } from 'lucide-react';
import TenureTableField from '@/components/compensation/tenure-table-field';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { signedYearly } from '@/lib/compensation/scenario-draft';
import type { TermChangeForm } from '@/lib/compensation/scenarios';

/**
 * A benefit the scenario changes, as a row to tick (board V4-Comp-Escenario): what it is today, what it adds a year and, while
 * ticked, its new value and seniority table. An unticked row stays on the list but is neither costed nor saved.
 */
export default function ScenarioBenefitRow({
    change,
    index,
    included,
    yearly,
    onToggle,
    onValue,
    onRemove,
}: {
    change: TermChangeForm;
    index: number;
    included: boolean;
    /** What it adds a year, as the server wrote it; unknown until it answers. */
    yearly: string | undefined;
    onToggle: () => void;
    onValue: (value: string) => void;
    onRemove: () => void;
}) {
    const field = `term_changes[${index}]`;

    return (
        <li className="grid gap-4 rounded-lg border border-input bg-card px-4 py-3.5 has-[input[type=checkbox]:checked]:border-2 has-[input[type=checkbox]:checked]:border-primary has-[input[type=checkbox]:checked]:bg-accent has-[input[type=checkbox]:checked]:px-[15px] has-[input[type=checkbox]:checked]:py-[13px]">
            <div className="flex items-start gap-3">
                <input
                    id={`scenario-benefit-${change.benefit_id}`}
                    type="checkbox"
                    checked={included}
                    onChange={onToggle}
                    className="mt-1 size-5 shrink-0 accent-primary"
                />
                <label
                    htmlFor={`scenario-benefit-${change.benefit_id}`}
                    className="flex min-w-0 flex-1 cursor-pointer flex-wrap justify-between gap-x-4 gap-y-1"
                >
                    <span className="min-w-0">
                        <span className="block font-medium">{change.name}</span>
                        <span className="block text-sm text-muted-foreground">
                            {change.current_text === null
                                ? 'Nueva en el contrato'
                                : `Hoy: ${change.current_text}`}
                        </span>
                    </span>
                    {included && yearly !== undefined && (
                        <span className="text-sm font-medium tabular-nums">
                            {signedYearly(yearly)}
                        </span>
                    )}
                </label>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Quitar ${change.name}`}
                    onClick={onRemove}
                    className="-my-1.5"
                >
                    <Trash2 />
                </Button>
            </div>
            <fieldset
                disabled={!included}
                hidden={!included}
                className="grid gap-4 sm:pl-8"
            >
                <legend className="sr-only">
                    Lo que cambia de {change.name}
                </legend>
                <input
                    type="hidden"
                    name={`${field}[benefit_id]`}
                    value={change.benefit_id}
                />
                <div className="sm:max-w-[260px]">
                    <FormField
                        name={`${field}[value]`}
                        idPrefix="scenario"
                        label={`Valor nuevo (${change.unit})`}
                        inputMode="decimal"
                        autoComplete="off"
                        value={change.value}
                        onChange={(event) => onValue(event.target.value)}
                    />
                </div>
                <details
                    open={change.tenure_table.length > 0}
                    className="group"
                >
                    <summary className="flex min-h-11 cursor-pointer items-center text-sm font-medium text-primary">
                        Cambia con la antigüedad
                    </summary>
                    <TenureTableField
                        initial={change.tenure_table}
                        unit={change.unit}
                        error={undefined}
                        namePrefix={`${field}[tenure_table]`}
                    />
                </details>
            </fieldset>
        </li>
    );
}
