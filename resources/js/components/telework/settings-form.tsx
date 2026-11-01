import { Form } from '@inertiajs/react';
import { CalendarClock, FileText } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import ReviewPeriodField from '@/components/telework/review-period-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/tenant/telework/settings';

/** The two NOM-037 settings of the company, saved together (pantallas: Ajustes). */
export default function SettingsForm({
    tenantSlug,
    periodMonths,
    presets,
    maxMonths,
    consent,
}: {
    tenantSlug: string;
    periodMonths: number;
    presets: number[];
    maxMonths: number;
    consent: { version: number; text: string };
}) {
    return (
        <Form
            {...update.form(tenantSlug)}
            options={{ preserveScroll: true }}
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <SettingRow
                        icon={CalendarClock}
                        title="Periodicidad"
                        description="Cada cuántos meses se vuelve a aplicar la Sección II de la lista de verificación a cada lugar de teletrabajo."
                    >
                        <ReviewPeriodField
                            presets={presets}
                            max={maxMonths}
                            defaultMonths={periodMonths}
                            error={errors['period_months']}
                        />
                    </SettingRow>
                    <SettingRow
                        icon={FileText}
                        title="Consentimiento"
                        status={
                            <span className="rounded-full bg-muted px-2.5 py-0.5 text-[13px] text-muted-foreground">
                                Versión {consent.version}
                            </span>
                        }
                        description="Lo aceptan las personas antes de contestar datos de salud o subir fotos. Si cambias el texto, se guarda como versión nueva y las aceptaciones anteriores conservan el suyo."
                    >
                        <textarea
                            name="consent_text"
                            aria-label="Texto del consentimiento"
                            defaultValue={consent.text}
                            rows={10}
                            className="min-h-48 w-full rounded-md border border-input bg-card px-4 py-3 text-base transition-[color,border-color,box-shadow] duration-150 ease-out hover:border-foreground/60 focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive"
                            aria-invalid={
                                errors['consent_text'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError
                            role="alert"
                            message={errors['consent_text']}
                        />
                    </SettingRow>
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Guardar ajustes
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
