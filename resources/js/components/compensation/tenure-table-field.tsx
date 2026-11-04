import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenureRows } from '@/hooks/use-tenure-rows';
import type { TenureFormRow } from '@/lib/compensation/benefits';

/**
 * The table of a benefit that grows with seniority (vacations, a premium by years): from each year of service on, a
 * value. The rows go from fewer years to more, and with none the benefit has the same value for everyone.
 */
export default function TenureTableField({
    initial,
    unit,
    error,
}: {
    initial: TenureFormRow[];
    unit: string;
    error: string | undefined;
}) {
    const { rows, add, remove, change } = useTenureRows(initial);

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm leading-5 font-medium">
                Tabla por antigüedad (opcional)
            </legend>
            <p className="text-caption text-muted-foreground">
                Si el valor crece con los años de servicio, agrega un renglón
                por cada cambio. Cada renglón vale desde los años que indica.
            </p>
            {rows.map((row, index) => (
                <div
                    // Rows have no identity beyond their place; they are retyped, added at the end and removed.
                    key={index}
                    className="grid grid-cols-[1fr_1fr_auto] items-center gap-2"
                >
                    <Input
                        name={`tenure_table[${index}][from_years]`}
                        aria-label={`Años de servicio del renglón ${index + 1}`}
                        placeholder="Desde (años)"
                        inputMode="numeric"
                        value={row.from_years}
                        onChange={(event) =>
                            change(index, { from_years: event.target.value })
                        }
                    />
                    <Input
                        name={`tenure_table[${index}][value]`}
                        aria-label={`Valor (${unit}) del renglón ${index + 1}`}
                        placeholder={`Valor (${unit})`}
                        inputMode="decimal"
                        value={row.value}
                        onChange={(event) =>
                            change(index, { value: event.target.value })
                        }
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Quitar el renglón ${index + 1}`}
                        onClick={() => remove(index)}
                    >
                        <Trash2 />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                onClick={add}
                className="justify-self-start"
            >
                <Plus />
                Agregar renglón
            </Button>
            <InputError message={error} />
        </fieldset>
    );
}
