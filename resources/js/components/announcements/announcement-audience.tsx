import { useState } from 'react';
import AnnouncementSelect from '@/components/announcements/announcement-select';
import type { AnnouncementOption, AnnouncementRow } from '@/types';

/** To whom an announcement goes (tablero Avisos: Destino): every company, or the ones on one plan of the catalog. */
export default function AnnouncementAudience({
    announcement,
    plans,
    errors,
}: {
    announcement: AnnouncementRow | null;
    plans: AnnouncementOption[];
    errors: Partial<Record<string, string>>;
}) {
    const [target, setTarget] = useState(
        announcement?.audience === 'plan' && announcement.plan !== null
            ? `plan:${announcement.plan}`
            : 'all',
    );
    const plan = target.startsWith('plan:') ? target.slice(5) : '';

    return (
        <>
            <AnnouncementSelect
                id="announcement-audience"
                label="Destino"
                field={{
                    options: [
                        { value: 'all', label: 'Todas las empresas' },
                        ...plans.map((option) => ({
                            value: `plan:${option.value}`,
                            label: `Plan ${option.label}`,
                        })),
                    ],
                    value: target,
                    onChange: setTarget,
                }}
                error={errors['audience'] ?? errors['plan']}
            />
            <input
                type="hidden"
                name="audience"
                value={plan === '' ? 'all' : 'plan'}
            />
            <input type="hidden" name="plan" value={plan} />
        </>
    );
}
