import { Form } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useYearStart } from '@/hooks/use-year-start';
import { describeDate } from '@/lib/calendar-date';
import { update } from '@/routes/tenant/compensation/risk-premium';
import type { RiskPremium } from '@/types';

/**
 * The IMSS work risk premium of the company, which the IMSS sets for each employer every year: without it the cost of
 * the work risk line is not worked out and the report says so.
 */
export default function RiskPremiumCard({
    premium,
}: {
    premium: RiskPremium | null;
}) {
    const tenantSlug = useTenantSlug();
    const start = useYearStart();

    return (
        <section className="grid gap-4 rounded-xl border bg-card p-5">
            <div className="flex items-start gap-3">
                <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                    <ShieldAlert aria-hidden className="size-5" />
                </span>
                <div>
                    <h2 className="text-lg leading-[26px] font-semibold">
                        Prima de riesgo de trabajo
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {premium === null
                            ? 'Todavía no la capturas: el costo de riesgo de trabajo no se calcula.'
                            : `${premium.rate} % del salario base de cotización, desde el ${describeDate(premium.effective_from)}.`}
                    </p>
                </div>
            </div>
            <Form
                {...update.form(tenantSlug)}
                noValidate
                options={{ preserveScroll: true }}
                className="grid items-start gap-4 sm:grid-cols-[1fr_1fr_auto]"
            >
                {({ processing, errors }) => (
                    <>
                        <FormField
                            name="rate"
                            idPrefix="premium"
                            label="Prima (%)"
                            inputMode="decimal"
                            autoComplete="off"
                            placeholder="Como 1.13065"
                            error={errors['rate']}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="premium-effective_from">
                                Vigente desde
                            </Label>
                            <DatePicker
                                id="premium-effective_from"
                                name="effective_from"
                                defaultValue={start}
                                invalid={errors['effective_from'] !== undefined}
                                describedBy="premium-effective_from-error"
                            />
                            <InputError
                                id="premium-effective_from-error"
                                message={errors['effective_from']}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="sm:mt-7"
                        >
                            {processing && <Spinner />}
                            Guardar prima
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
