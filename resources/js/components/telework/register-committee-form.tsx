import { Form } from '@inertiajs/react';
import { CalendarCheck, FileText } from 'lucide-react';
import DatePicker from '@/components/humetria/date-picker';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import MemberChoices from '@/components/telework/member-choices';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/committee';
import type { CommitteePerson } from '@/types';

/** Registers the Comisión of the center: its act date, the act's PDF and who sits on it (pantallas: Ajustes). */
export default function RegisterCommitteeForm({
    tenantSlug,
    workCenterId,
    candidates,
}: {
    tenantSlug: string;
    workCenterId: string;
    candidates: CommitteePerson[];
}) {
    return (
        <Form
            {...store.form({ tenant: tenantSlug, workCenter: workCenterId })}
            noValidate
            className="flex flex-col gap-7 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0"
        >
            {({ processing, errors }) => (
                <>
                    <SettingRow
                        icon={CalendarCheck}
                        title="Fecha del acta"
                        description="El día en que se constituyó la Comisión de seguridad e higiene de este centro."
                    >
                        <DatePicker
                            id="committee-constituted-on"
                            name="constituted_on"
                            invalid={errors['constituted_on'] !== undefined}
                            describedBy="committee-constituted-on-error"
                        />
                        <InputError
                            id="committee-constituted-on-error"
                            role="alert"
                            message={errors['constituted_on']}
                        />
                    </SettingRow>
                    <SettingRow
                        icon={FileText}
                        title="Acta en PDF"
                        status={
                            <span className="rounded-full bg-muted px-2.5 py-0.5 text-[13px] text-muted-foreground">
                                Opcional
                            </span>
                        }
                        description="Súbela si la tienes a la mano; puedes registrar la Comisión sin ella."
                    >
                        <input
                            name="act"
                            type="file"
                            accept="application/pdf"
                            aria-label="Acta en PDF"
                            aria-invalid={
                                errors['act'] === undefined ? undefined : true
                            }
                            className="w-full rounded-md border border-input bg-card px-4 py-2.5 text-base file:mr-3 file:font-medium"
                        />
                        <InputError role="alert" message={errors['act']} />
                    </SettingRow>
                    <MemberChoices
                        candidates={candidates}
                        error={errors['member_ids'] ?? errors['member_ids.0']}
                        centerError={errors['work_center']}
                    />
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Registrar Comisión
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
