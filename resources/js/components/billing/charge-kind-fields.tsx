import ChoiceField from '@/components/humetria/choice-field';
import type { ChargeKind } from '@/lib/billing-form';

const KINDS: { value: ChargeKind; label: string }[] = [
    { value: 'one_time', label: 'Una sola vez' },
    { value: 'recurring', label: 'Recurrente' },
];

/** Whether a charge is made once (an implementation, a study) or every period (the accompaniment). */
export default function ChargeKindFields({
    kind,
    onChange,
    error,
}: {
    kind: ChargeKind;
    onChange: (kind: ChargeKind) => void;
    error: string | undefined;
}) {
    return (
        <ChoiceField
            name="kind"
            legend="Tipo de cargo"
            options={KINDS}
            value={kind}
            columns={2}
            error={error}
            onChange={onChange}
        />
    );
}
