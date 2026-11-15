import FormField from '@/components/form-field';
import ChoiceField from '@/components/humetria/choice-field';
import { STATUSES } from '@/lib/billing-form';
import type { SubscriptionStatus } from '@/types';

/** What the company pays for: the people and centers it contracted (a cap, not the month's peak), and where the plan stands. */
export default function SubscriptionSizeFields({
    seats,
    centers,
    status,
    onStatus,
    errors,
}: {
    seats: string;
    centers: string;
    status: SubscriptionStatus;
    onStatus: (status: SubscriptionStatus) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    idPrefix="plan"
                    name="seats"
                    label="Personas contratadas"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    defaultValue={seats}
                    error={errors['seats']}
                />
                <FormField
                    idPrefix="plan"
                    name="centers"
                    label="Centros de trabajo"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    defaultValue={centers}
                    error={errors['centers']}
                />
            </div>
            <ChoiceField
                name="status"
                legend="Estado del plan"
                options={STATUSES}
                value={status}
                columns={3}
                error={errors['status']}
                onChange={onStatus}
            />
        </div>
    );
}
