import { Archive, Crosshair, QrCode } from 'lucide-react';
import PolicyOutOfZone from '@/components/attendance/policy-out-of-zone';
import PolicyPrecision from '@/components/attendance/policy-precision';
import PolicySwitch from '@/components/attendance/policy-switch';
import FormField from '@/components/form-field';
import SettingRow from '@/components/humetria/setting-row';
import type { AttendancePolicy } from '@/types';

/** The four settings of the policy, each with its icon: what they hold is `draft`, what changes goes to `onChange`. */
export default function PolicyFields({
    draft,
    onChange,
    errors,
}: {
    draft: AttendancePolicy;
    onChange: (patch: Partial<AttendancePolicy>) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <PolicyOutOfZone
                value={draft.outOfZone}
                onChange={(outOfZone) => onChange({ outOfZone })}
                error={errors['out_of_zone']}
            />
            <SettingRow
                icon={Crosshair}
                title="Precisión de la ubicación"
                description="Una ubicación menos precisa que esto no cuenta como dentro de la zona: la marca baja su nivel de evidencia."
            >
                <PolicyPrecision
                    value={draft.maxAccuracyM}
                    onChange={(maxAccuracyM) => onChange({ maxAccuracyM })}
                    error={errors['max_accuracy_m']}
                />
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
                    checked={draft.requireQrOnsite}
                    onChange={(requireQrOnsite) =>
                        onChange({ requireQrOnsite })
                    }
                />
                <PolicySwitch
                    name="trusted_may_use_browser"
                    label="El personal de confianza puede marcar desde su navegador"
                    hint="Sin QR ni zona: queda como evidencia de navegador de confianza."
                    checked={draft.trustedMayUseBrowser}
                    onChange={(trustedMayUseBrowser) =>
                        onChange({ trustedMayUseBrowser })
                    }
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
                        value={draft.retentionMonths}
                        onChange={(event) =>
                            onChange({
                                retentionMonths: Number(event.target.value),
                            })
                        }
                        error={errors['retention_months']}
                    />
                </div>
            </SettingRow>
        </>
    );
}
