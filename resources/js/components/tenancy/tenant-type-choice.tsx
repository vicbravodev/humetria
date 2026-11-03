import InputError from '@/components/humetria/input-error';

const TYPES = [
    {
        value: 'company',
        label: 'Empresa',
        hint: 'Cumple las normas de RH y puede costear su contrato colectivo.',
    },
    {
        value: 'union',
        label: 'Sindicato',
        hint: 'Solo costea una propuesta de revisión de contrato colectivo.',
    },
];

/** The kind of account, chosen when it is created and read-only after. A company is chosen to begin with. */
export default function TenantTypeChoice({
    error,
}: {
    error: string | undefined;
}) {
    return (
        <fieldset
            aria-describedby={
                error === undefined ? undefined : 'tenant-type-error'
            }
        >
            <legend className="mb-2 text-sm font-medium">Tipo de cuenta</legend>
            <div className="flex flex-wrap items-stretch gap-2.5">
                {TYPES.map((type) => (
                    <label
                        key={type.value}
                        className="flex min-h-14 flex-[1_1_200px] cursor-pointer items-start gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                    >
                        <input
                            type="radio"
                            name="type"
                            value={type.value}
                            defaultChecked={type.value === 'company'}
                            className="mt-0.5 size-5 shrink-0 accent-primary"
                        />
                        <span className="flex flex-col">
                            <span className="font-medium">{type.label}</span>
                            <span className="text-sm text-muted-foreground">
                                {type.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError id="tenant-type-error" message={error} />
        </fieldset>
    );
}
