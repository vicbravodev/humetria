import PillChoice from '@/components/humetria/pill-choice';
import type { PayBasis } from '@/types';

const BASES: { value: PayBasis; label: string }[] = [
    { value: 'daily', label: 'Por día (365 días al año)' },
    { value: 'monthly', label: 'Por mes (12 meses)' },
];

/** How the salaries of the roster were agreed, which decides how many days of salary a year costs. */
export default function ScenarioPayBasisField({
    value,
    onChange,
}: {
    value: PayBasis;
    onChange: (value: PayBasis) => void;
}) {
    return (
        <PillChoice
            name="pay_basis"
            legend="¿Cómo se pactan los sueldos?"
            options={BASES}
            value={value}
            onChange={onChange}
        />
    );
}
