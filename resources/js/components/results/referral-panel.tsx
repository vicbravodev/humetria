import { Form } from '@inertiajs/react';
import { useState } from 'react';
import Initials from '@/components/humetria/initials';
import InputError from '@/components/humetria/input-error';
import ReferralChoices from '@/components/results/referral-choices';
import ReferralRecordFields from '@/components/results/referral-record-fields';
import ReferralToldField from '@/components/results/referral-told-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/campaigns/screenings';
import type {
    DestinationOption,
    ReferralDestination,
    ReferralTold,
    SelectedPerson,
} from '@/types';

/**
 * The person open beside the list (board V4-N035-Canalizacion): where to channel them, how to tell them with what to say,
 * and the record. Nothing about what they answered: only that their Guía I asks for an assessment.
 */
export default function ReferralPanel({
    campaignId,
    person,
    destinations,
    today,
}: {
    campaignId: string;
    person: SelectedPerson;
    destinations: DestinationOption[];
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const [destination, setDestination] = useState<ReferralDestination | null>(
        person.referral?.destination ?? null,
    );
    const [told, setTold] = useState<ReferralTold>(
        person.referral?.told ?? 'in_person',
    );
    const chosen = destinations.find((each) => each.value === destination);

    return (
        <section
            aria-labelledby="referral-person"
            className="@container flex flex-col gap-7 card p-5 md:p-7"
        >
            <header className="flex items-center gap-3.5">
                <Initials name={person.name} className="size-11" />
                <div className="min-w-0">
                    <h2 id="referral-person" className="text-xl font-semibold">
                        {person.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {[
                            person.jobTitle,
                            person.area,
                            `contestó la Guía I el ${person.answeredOn}`,
                        ]
                            .filter((part) => part !== '')
                            .join(' · ')}
                    </p>
                </div>
            </header>
            <Form
                {...update.form([tenantSlug, campaignId, person.id])}
                options={{ preserveScroll: true }}
                noValidate
                className="grid gap-7"
            >
                {({ processing, errors }) => (
                    <>
                        <ReferralChoices
                            name="destination"
                            legend="1. ¿A dónde la canalizas?"
                            options={destinations}
                            value={destination}
                            onChange={setDestination}
                            columns={2}
                        />
                        <InputError message={errors['destination']} />
                        <ReferralToldField
                            told={told}
                            onChange={setTold}
                            reachable={person.reachable}
                            destination={chosen}
                        />
                        <ReferralRecordFields
                            referral={person.referral}
                            today={today}
                            errors={errors}
                        />
                        <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-5">
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Si la persona no quiere atenderse, regístralo
                                así: también cuenta como canalizada.
                            </p>
                            <Button
                                type="submit"
                                disabled={processing || destination === null}
                            >
                                {processing && <Spinner />}
                                {person.referral === null
                                    ? 'Registrar canalización'
                                    : 'Guardar cambios'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
