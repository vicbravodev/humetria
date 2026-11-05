import { Form } from '@inertiajs/react';
import { Clock, ScrollText } from 'lucide-react';
import ChannelNumberField from '@/components/complaints/channel-number-field';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { activate, update } from '@/routes/tenant/complaints/channel';
import type { ChannelBounds, ChannelSettings } from '@/types';

/** The written procedure and the deadlines of the channel; saving them activates a channel that was not yet, or changes an active one. */
export default function ChannelSettingsForm({
    settings,
    bounds,
    activating,
    onSaved,
}: {
    settings: ChannelSettings;
    bounds: ChannelBounds;
    activating: boolean;
    onSaved?: () => void;
}) {
    const tenantSlug = useTenantSlug();
    const route = activating ? activate : update;

    return (
        <Form
            {...route.form(tenantSlug)}
            options={{ preserveScroll: true }}
            onSuccess={() => onSaved?.()}
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <SettingRow
                        icon={ScrollText}
                        title="Procedimiento"
                        description="Cómo se atiende una queja. Las personas lo leen antes de presentarla. Es un modelo: ajústalo a lo que tu empresa de verdad hace."
                    >
                        <Textarea
                            name="procedure"
                            aria-label="Procedimiento de atención"
                            defaultValue={settings.procedure}
                            rows={10}
                            aria-invalid={
                                errors['procedure'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError message={errors['procedure']} />
                    </SettingRow>
                    <SettingRow
                        icon={Clock}
                        title="Plazos y conservación"
                        description="Son criterios de Humetría, no plazos de la norma: sirven de recordatorio para quien atiende y se muestran a las personas."
                    >
                        <div className="grid gap-4 sm:grid-cols-3">
                            <ChannelNumberField
                                name="acknowledge_within_days"
                                label="Acusar recibo en (días)"
                                value={settings.acknowledgeWithinDays}
                                bound={bounds.acknowledgeWithinDays}
                                error={errors['acknowledge_within_days']}
                            />
                            <ChannelNumberField
                                name="resolve_within_days"
                                label="Responder en (días)"
                                value={settings.resolveWithinDays}
                                bound={bounds.resolveWithinDays}
                                error={errors['resolve_within_days']}
                            />
                            <ChannelNumberField
                                name="retention_months"
                                label="Conservar tras el cierre (meses)"
                                value={settings.retentionMonths}
                                bound={bounds.retentionMonths}
                                error={errors['retention_months']}
                            />
                        </div>
                    </SettingRow>
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            {activating
                                ? 'Activar el canal'
                                : 'Guardar ajustes'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
