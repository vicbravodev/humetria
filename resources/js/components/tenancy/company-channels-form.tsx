import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import ChoiceField from '@/components/humetria/choice-field';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import WhatsAppChannelRow from '@/components/tenancy/whatsapp-channel-row';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useChannelsChoice } from '@/hooks/use-channels-choice';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { channelsPayload } from '@/lib/channels';
import { update } from '@/routes/tenant/channels';
import type { CompanyChannelsPage } from '@/types';

/** The channels through which the company's people are written to: each on or off, which goes first and what WhatsApp adds. */
export default function CompanyChannelsForm({
    page,
}: {
    page: CompanyChannelsPage;
}) {
    const tenantSlug = useTenantSlug();
    const { choice, change } = useChannelsChoice(page);
    const both =
        choice.mail &&
        choice.whatsapp &&
        page.whatsAppAvailable &&
        page.hasPlan;

    return (
        <Form
            {...update.form(tenantSlug)}
            transform={() => channelsPayload(choice)}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <SettingRow
                        icon={Mail}
                        title="Correo"
                        description="El canal de siempre: sin costo adicional."
                    >
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="channel-mail"
                                checked={choice.mail}
                                onCheckedChange={(next) =>
                                    change({ mail: next === true })
                                }
                            />
                            <Label htmlFor="channel-mail">
                                Mandar por correo
                            </Label>
                        </div>
                        <InputError message={errors['mail_enabled']} />
                    </SettingRow>
                    {page.whatsAppAvailable && (
                        <WhatsAppChannelRow
                            page={page}
                            choice={choice}
                            onChange={change}
                            errors={errors}
                        />
                    )}
                    {both && (
                        <ChoiceField
                            name="preferred"
                            legend="Canal preferido"
                            hint="Cada persona recibe su invitación por un solo canal: el primero para el que tenemos su dato. Si WhatsApp falla, le llega por correo."
                            options={[
                                {
                                    value: 'mail' as const,
                                    label: 'Correo primero',
                                },
                                {
                                    value: 'whatsapp' as const,
                                    label: 'WhatsApp primero',
                                },
                            ]}
                            value={choice.preferred}
                            onChange={(preferred) => change({ preferred })}
                            error={errors['preferred']}
                        />
                    )}
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
