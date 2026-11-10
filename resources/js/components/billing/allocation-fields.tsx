import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOpenStatements } from '@/hooks/use-open-statements';

/**
 * Which statements the payment settles and for how much each: every issued statement with a balance, oldest first, with an
 * amount to type (blank leaves it alone). What is not applied stays as an unapplied part of the payment.
 */
export default function AllocationFields({
    accountId,
    error,
}: {
    accountId: string;
    error: string | undefined;
}) {
    const statements = useOpenStatements(accountId);

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Aplicar a estados de cuenta
            </legend>
            {statements === null && (
                <p className="text-sm text-muted-foreground">
                    Buscando estados de cuenta con saldo…
                </p>
            )}
            {statements?.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    La cuenta no tiene estados de cuenta con saldo. El pago
                    quedará sin aplicar.
                </p>
            )}
            {statements?.map((statement) => (
                <div
                    key={statement.id}
                    className="grid grid-cols-[1fr_9rem] items-center gap-3"
                >
                    <Label htmlFor={`allocation-${statement.id}`}>
                        {statement.label}
                        <span className="block text-caption font-normal text-muted-foreground">
                            Saldo {statement.balance}
                        </span>
                    </Label>
                    <Input
                        id={`allocation-${statement.id}`}
                        name={`allocations[${statement.id}]`}
                        inputMode="decimal"
                        autoComplete="off"
                        placeholder={statement.balance_input}
                    />
                </div>
            ))}
            <InputError id="allocations-error" message={error} />
        </fieldset>
    );
}
