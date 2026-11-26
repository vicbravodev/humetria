import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import type { AnnouncementOption, AnnouncementRow } from '@/types';

const AUDIENCES = [
    { value: 'all', label: 'Todas las empresas' },
    { value: 'plan', label: 'Las de un plan' },
];

/** To whom an announcement goes: every company, or the ones on one plan of the catalog. */
export default function AnnouncementAudience({
    announcement,
    plans,
    errors,
}: {
    announcement: AnnouncementRow | null;
    plans: AnnouncementOption[];
    errors: Partial<Record<string, string>>;
}) {
    const [audience, setAudience] = useState<string>(
        announcement?.audience ?? 'all',
    );
    const [plan, setPlan] = useState(announcement?.plan ?? null);

    return (
        <>
            <ChoiceField
                name="audience"
                legend="A quién"
                options={AUDIENCES}
                value={audience}
                onChange={setAudience}
                columns={2}
                error={errors['audience']}
            />
            {audience === 'plan' && (
                <ChoiceField
                    name="plan"
                    legend="Plan"
                    options={plans}
                    value={plan}
                    onChange={setPlan}
                    columns={3}
                    error={errors['plan']}
                />
            )}
        </>
    );
}
