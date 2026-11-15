import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import RowField from '@/components/leave/policy-row-field';
import { floorText } from '@/lib/leave/policy';
import type { PolicyRowDraft } from '@/lib/leave/policy';

type Errors = Partial<Record<string, string>>;

/**
 * A row of the policy: the days of a range of years of service, with the minimum of the law for the last year beside them, or
 * days that come every year with a name of their own. The error of each field shows under it.
 */
export default function PolicyRowFields({
    row,
    index,
    floors,
    errors,
    onChange,
    onRemove,
}: {
    row: PolicyRowDraft;
    index: number;
    floors: number[];
    errors: Errors;
    onChange: (patch: Partial<PolicyRowDraft>) => void;
    onRemove: () => void;
}) {
    const at = (field: string) => `rows[${index}][${field}]`;
    const error = (field: string) => errors[`rows.${index}.${field}`];
    const tier = row.kind === 'tier';

    return (
        <li className="grid gap-3 rounded-lg border bg-card p-4">
            <input type="hidden" name={at('kind')} value={row.kind} />
            <div className="grid items-start gap-3 sm:grid-cols-[repeat(3,minmax(0,1fr))_minmax(0,1.3fr)_auto]">
                {tier ? (
                    <>
                        <RowField
                            id={`row-${row.key}-from`}
                            label="Del año"
                            name={at('year_from')}
                            value={row.yearFrom}
                            error={error('year_from')}
                            onChange={(yearFrom) => onChange({ yearFrom })}
                        />
                        <RowField
                            id={`row-${row.key}-to`}
                            label="Al año"
                            name={at('year_to')}
                            value={row.yearTo}
                            error={error('year_to')}
                            onChange={(yearTo) => onChange({ yearTo })}
                        />
                    </>
                ) : (
                    <div className="sm:col-span-2">
                        <RowField
                            id={`row-${row.key}-label`}
                            label="Nombre de estos días"
                            name={at('label')}
                            value={row.label}
                            error={error('label')}
                            onChange={(label) => onChange({ label })}
                            type="text"
                        />
                    </div>
                )}
                <RowField
                    id={`row-${row.key}-days`}
                    label={tier ? 'Días en total' : 'Días cada año'}
                    name={at('days')}
                    value={row.days}
                    error={error('days')}
                    onChange={(days) => onChange({ days })}
                />
                {tier && (
                    <p className="text-caption text-muted-foreground sm:mt-[30px]">
                        {floorText(floors, row.yearTo) ||
                            'Escribe el último año para ver el mínimo de la ley.'}
                    </p>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Quitar renglón"
                    onClick={onRemove}
                    className="sm:mt-[26px]"
                >
                    <Trash2 />
                </Button>
            </div>
        </li>
    );
}
