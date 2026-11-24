import { MessageCircle } from 'lucide-react';
import FormField from '@/components/form-field';
import InputError from '@/components/humetria/input-error';
import Notice from '@/components/humetria/notice';
import SettingRow from '@/components/humetria/setting-row';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { needsConsent } from '@/lib/channels';
import type { ChannelChoice } from '@/lib/channels';
import type { CompanyChannelsPage } from '@/types';

/** WhatsApp in "Canales de comunicación": on or off, the extra cost, the words the owner accepts and the monthly cap. */
export default function WhatsAppChannelRow({
    page,
    choice,
    onChange,
    errors,
}: {
    page: CompanyChannelsPage;
    choice: ChannelChoice;
    onChange: (part: Partial<ChannelChoice>) => void;
    errors: Record<string, string>;
}) {
    const blocked = !page.hasPlan;

    return (
        <SettingRow
            icon={MessageCircle}
            title="WhatsApp"
            description="Las invitaciones a las encuestas llegan al celular de tu gente, desde el número de Humetría."
        >
            {blocked ? (
                <Notice>
                    <strong>Activa un plan para usar WhatsApp.</strong>
                </Notice>
            ) : (
                <Notice>{page.costNotice}</Notice>
            )}
            <div className="flex items-center gap-3">
                <Checkbox
                    id="channel-whatsapp"
                    checked={choice.whatsapp && !blocked}
                    disabled={blocked}
                    onCheckedChange={(next) =>
                        onChange({ whatsapp: next === true })
                    }
                />
                <Label htmlFor="channel-whatsapp">Mandar por WhatsApp</Label>
            </div>
            <InputError message={errors['whatsapp_enabled']} />
            {choice.whatsapp && !blocked && (
                <>
                    <FormField
                        name="whatsapp_monthly_cap"
                        label="Tope mensual de mensajes (opcional)"
                        idPrefix="channel"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={100000}
                        value={choice.cap}
                        onChange={(event) =>
                            onChange({ cap: event.target.value })
                        }
                        error={errors['whatsapp_monthly_cap']}
                    />
                    <p className="text-sm text-muted-foreground tabular-nums">
                        Este mes llevas {page.whatsAppQuota.used} de{' '}
                        {page.whatsAppQuota.quota} mensajes que incluye tu plan.
                    </p>
                </>
            )}
            {needsConsent(page, choice) && !blocked && (
                <div className="flex items-start gap-3 rounded-lg border bg-card p-4">
                    <Checkbox
                        id="channel-consent"
                        checked={choice.accepted}
                        onCheckedChange={(next) =>
                            onChange({ accepted: next === true })
                        }
                        aria-invalid={
                            errors['consent_accepted'] === undefined
                                ? undefined
                                : true
                        }
                        className="mt-0.5"
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="channel-consent">Acepto el aviso</Label>
                        <p className="text-sm text-pretty text-muted-foreground">
                            {page.consent.text}
                        </p>
                        <InputError message={errors['consent_accepted']} />
                    </div>
                </div>
            )}
        </SettingRow>
    );
}
