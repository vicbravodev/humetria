import SubscriptionPlanFields from '@/components/billing/subscription-plan-fields';
import SubscriptionPreviewPanel from '@/components/billing/subscription-preview-panel';
import SubscriptionPriceFields from '@/components/billing/subscription-price-fields';
import SubscriptionSizeFields from '@/components/billing/subscription-size-fields';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { PlanDefaults } from '@/lib/billing-form';
import type {
    AccountKind,
    Plan,
    PriceProfile,
    SubscriptionPreview,
    SubscriptionStatus,
} from '@/types';

/** The fields of a company's plan, the price beside them and the buttons: what the form shows, with its state held above. */
export default function SubscriptionFormBody({
    defaults,
    accountKind,
    choice,
    status,
    preview,
    errors,
    processing,
    onChoice,
    onStatus,
}: {
    defaults: PlanDefaults;
    accountKind: AccountKind;
    choice: { plan: Plan; profile: PriceProfile };
    status: SubscriptionStatus;
    preview: SubscriptionPreview | null;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    onChoice: (next: { plan: Plan; profile: PriceProfile }) => void;
    onStatus: (status: SubscriptionStatus) => void;
}) {
    return (
        <>
            <div className="grid items-start gap-6 md:grid-cols-[minmax(0,1fr)_300px]">
                <div className="grid gap-5">
                    <SubscriptionPlanFields
                        accountKind={accountKind}
                        plan={choice.plan}
                        profile={choice.profile}
                        onChange={onChoice}
                        errors={errors}
                    />
                    <SubscriptionSizeFields
                        seats={defaults.seats}
                        centers={defaults.centers}
                        status={status}
                        onStatus={onStatus}
                        errors={errors}
                    />
                    <SubscriptionPriceFields
                        startsOn={defaults.startsOn}
                        price={defaults.price}
                        validUntil={defaults.validUntil}
                        errors={errors}
                    />
                </div>
                <SubscriptionPreviewPanel preview={preview} />
            </div>
            <DialogFooter className="gap-2">
                <DialogClose asChild>
                    <Button variant="outline">Cancelar</Button>
                </DialogClose>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Guardar plan
                </Button>
            </DialogFooter>
        </>
    );
}
