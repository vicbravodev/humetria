import InputError from '@/components/humetria/input-error';

const KINDS = [
    {
        value: 'direct',
        label: 'Directa',
        hint: 'Una empresa que paga por sí misma o por su grupo.',
    },
    {
        value: 'consultant',
        label: 'Consultor',
        hint: 'Paga las empresas de sus clientes, con tarifa de canal.',
    },
];

/** Who pays: chosen when the account is created, and read-only after, since its prices depend on it. */
export default function AccountKindChoice({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <fieldset
            aria-describedby={
                error === undefined ? undefined : 'billing-kind-error'
            }
        >
            <legend className="mb-2 text-sm font-medium">Tipo de cuenta</legend>
            <div className="flex flex-wrap items-stretch gap-2.5">
                {KINDS.map((kind) => (
                    <label
                        key={kind.value}
                        className="flex min-h-14 flex-[1_1_200px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name="kind"
                            value={kind.value}
                            defaultChecked={kind.value === 'direct'}
                            className="mt-0.5 size-5 shrink-0 accent-primary"
                        />
                        <span className="flex flex-col">
                            <span className="font-medium">{kind.label}</span>
                            <span className="text-sm text-muted-foreground">
                                {kind.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError id="billing-kind-error" message={error} />
        </fieldset>
    );
}
