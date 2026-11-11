import { Form } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { update } from '@/routes/tenant/vacations/settings';
import type { LeaveSettingData, LeaveWorkWeek } from '@/types';

/**
 * The working week the law's policy counts days by, and since which day the periods are granted: that day is shown and not
 * edited (what came before enters by CSV).
 */
export default function WeekCard({
    setting,
    workWeeks,
    canManage,
}: {
    setting: LeaveSettingData;
    workWeeks: { value: LeaveWorkWeek; label: string }[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [week, setWeek] = useState(setting.statutoryWorkWeek);

    return (
        <section
            aria-label="Semana y arranque"
            className="grid gap-6 card p-[22px]"
        >
            <div className="flex items-start gap-3">
                <Lock
                    aria-hidden
                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                />
                <div>
                    <h2 className="text-lg leading-[26px] font-semibold">
                        Arranque
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Los periodos se otorgan desde el{' '}
                        {describeDate(setting.trackingSince)}. Los anteriores
                        entran por CSV, y esta fecha no se cambia.
                    </p>
                </div>
            </div>
            <Form
                {...update.form(tenantSlug)}
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <ChoiceField
                            name="statutory_work_week"
                            legend="Semana laboral de la ley"
                            options={workWeeks}
                            value={week}
                            onChange={setWeek}
                            error={errors['statutory_work_week']}
                            columns={2}
                        />
                        {canManage && (
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar semana
                                </Button>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </section>
    );
}
