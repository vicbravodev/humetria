import { Form } from '@inertiajs/react';
import { ListPlus } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { propose } from '@/routes/tenant/seating/measures';
import type { MeasureSuggestions, SeatOption } from '@/types';

/** The Comisión proposes a measure from the assessment; RH follows it up in the plan. The suggestions are Humetría's. */
export default function ProposeMeasureForm({
    assessmentId,
    kinds,
    suggestions,
}: {
    assessmentId: string;
    kinds: SeatOption[];
    suggestions: MeasureSuggestions | null;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <SettingRow
            icon={ListPlus}
            title="Proponer una medida"
            description={
                suggestions === null
                    ? 'Propón las medidas que correspondan a esta persona.'
                    : `${suggestions.label}: ${suggestions.measures.map((measure) => measure.label.toLowerCase()).join('; ')}.`
            }
        >
            <Form
                {...propose.form([tenantSlug, assessmentId])}
                options={{ preserveScroll: true }}
                resetOnSuccess
                noValidate
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <SeatChoiceField
                            name="kind"
                            legend="Medida"
                            options={kinds}
                            value={undefined}
                            error={errors['kind']}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="propose-note">
                                Qué es (obligatorio si es «otra medida»)
                            </Label>
                            <Textarea id="propose-note" name="note" rows={2} />
                            <InputError message={errors['note']} />
                        </div>
                        <Button
                            variant="outline"
                            className="w-fit"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Proponer medida
                        </Button>
                    </>
                )}
            </Form>
        </SettingRow>
    );
}
