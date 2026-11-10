import { Plus } from 'lucide-react';
import { useState } from 'react';
import PolicyRowFields from '@/components/leave/policy-row-fields';
import { Button } from '@/components/ui/button';
import { blankRow, draftsOf } from '@/lib/leave/policy';
import type { PolicyRowDraft } from '@/lib/leave/policy';
import type { LeavePolicyRowData } from '@/types';

/**
 * The rows of a policy: the days for each range of years of service and, if the company gives them, days that come every
 * year. Beside every range, the minimum of the law for its last year; a row can only improve it.
 */
export default function PolicyRowsEditor({
    initial,
    floors,
    errors,
}: {
    initial: LeavePolicyRowData[];
    floors: number[];
    errors: Partial<Record<string, string>>;
}) {
    const [rows, setRows] = useState(() => draftsOf(initial));
    const add = (kind: PolicyRowDraft['kind']) =>
        setRows((current) => [
            ...current,
            blankRow(
                kind,
                Math.max(-1, ...current.map((row) => row.key)) + 1,
                current,
            ),
        ]);
    const change = (key: number, patch: Partial<PolicyRowDraft>) =>
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, ...patch } : row,
            ),
        );

    return (
        <section aria-labelledby="policy-rows" className="grid gap-4">
            <div>
                <h2
                    id="policy-rows"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Días por antigüedad
                </h2>
                <p className="text-sm text-muted-foreground">
                    Los años sin renglón siguen la ley.
                </p>
            </div>
            {rows.length > 0 && (
                <ul className="grid gap-3">
                    {rows.map((row, index) => (
                        <PolicyRowFields
                            key={row.key}
                            row={row}
                            index={index}
                            floors={floors}
                            errors={errors}
                            onChange={(patch) => change(row.key, patch)}
                            onRemove={() =>
                                setRows((current) =>
                                    current.filter(
                                        (other) => other.key !== row.key,
                                    ),
                                )
                            }
                        />
                    ))}
                </ul>
            )}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => add('tier')}
                >
                    <Plus />
                    Agregar rango de años
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => add('fixed')}
                >
                    <Plus />
                    Agregar días fijos
                </Button>
            </div>
        </section>
    );
}
