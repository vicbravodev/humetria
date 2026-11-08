import { Form } from '@inertiajs/react';
import { Archive, Crosshair, QrCode } from 'lucide-react';
import PolicyOutOfZone from '@/components/attendance/policy-out-of-zone';
import PolicySwitch from '@/components/attendance/policy-switch';
import FormField from '@/components/form-field';
import SettingRow from '@/components/humetria/setting-row';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/attendance/policy';
import type { AttendancePolicy } from '@/types';

/** The company's policy, in four settings; read-only (all of it disabled, no button) for whoever may not change it. */
export default function PolicyForm({
    policy,
    canManage,
}: {
    policy: AttendancePolicy;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...update.form(tenantSlug)}
            options={{ preserveScroll: true }}
            noValidate
        >
            {({ processing, errors }) => (
                <fieldset disabled={!canManage} className="flex flex-col gap-7">
                    <legend className="sr-only">Política de asistencia</legend>
                    <PolicyOutOfZone
                        value={policy.outOfZone}
                        error={errors['out_of_zone']}
                    />
                    <SettingRow
                        icon={Crosshair}
                        title="Precisión de la ubicación"
                        description="Una ubicación menos precisa que esto no cuenta como dentro de la zona: la marca baja su nivel de evidencia."
                    >
                        <div className="sm:max-w-80">
                            <FormField
                                name="max_accuracy_m"
                                label="Precisión máxima (metros, de 10 a 500)"
                                idPrefix="policy"
                                type="number"
                                inputMode="numeric"
                                min={10}
                                max={500}
                                defaultValue={policy.maxAccuracyM}
                                error={errors['max_accuracy_m']}
                            />
                        </div>
                    </SettingRow>
                    <SettingRow
                        icon={QrCode}
                        title="Cómo se marca"
                        description="La pantalla del QR prueba que la persona está en el centro. El personal de confianza puede marcar sin ella, desde su navegador, si lo permites."
                    >
                        <PolicySwitch
                            name="require_qr_onsite"
                            label="Pedir el QR a quien marca en el centro"
                            hint="Escanea el código de la pantalla de la entrada además de su ubicación."
                            checked={policy.requireQrOnsite}
                        />
                        <PolicySwitch
                            name="trusted_may_use_browser"
                            label="El personal de confianza puede marcar desde su navegador"
                            hint="Sin QR ni zona: queda como evidencia de navegador de confianza."
                            checked={policy.trustedMayUseBrowser}
                        />
                    </SettingRow>
                    <SettingRow
                        icon={Archive}
                        title="Conservación"
                        description="La ley pide conservar el registro mientras dure la relación de trabajo y un año después; puedes ampliarlo. Hoy Humetría no borra nada: este plazo se dice a cada persona al aceptar el registro."
                    >
                        <div className="sm:max-w-80">
                            <FormField
                                name="retention_months"
                                label="Meses después de la baja (12 a 120)"
                                idPrefix="policy"
                                type="number"
                                inputMode="numeric"
                                min={12}
                                max={120}
                                defaultValue={policy.retentionMonths}
                                error={errors['retention_months']}
                            />
                        </div>
                    </SettingRow>
                    {canManage && (
                        <div className="flex justify-end border-t pt-[18px]">
                            <Button disabled={processing}>
                                {processing && <Spinner />}
                                Guardar política
                            </Button>
                        </div>
                    )}
                </fieldset>
            )}
        </Form>
    );
}
