import Initials from '@/components/humetria/initials';
import ReferralDialog from '@/components/results/referral-dialog';
import ReferralStepper from '@/components/results/referral-stepper';
import { referralStep } from '@/lib/referral';
import type { ScreenedPerson as Person } from '@/types';

/** One person who requires a clinical assessment: who they are, where their channeling stands and what HR records next. */
export default function ScreenedPerson({
    campaignId,
    person,
}: {
    campaignId: string;
    person: Person;
}) {
    return (
        <li className="flex flex-wrap items-center gap-x-6 gap-y-4 card px-[22px] py-5">
            <Initials name={person.name} className="size-10 text-caption" />
            <div className="flex min-w-0 flex-[1_1_180px] flex-col">
                <p className="font-medium">{person.name}</p>
                <p className="text-caption text-muted-foreground">
                    {person.area} · {person.jobTitle} · contestó el{' '}
                    {person.answeredOn}
                </p>
            </div>
            <div className="flex flex-col gap-1 sm:w-96">
                <ReferralStepper step={referralStep(person.referral)} />
                {person.referral !== null && (
                    <p className="text-caption text-muted-foreground">
                        Canalizada el {person.referral.referredOnLabel} ·{' '}
                        {person.referral.institution}
                    </p>
                )}
            </div>
            <ReferralDialog campaignId={campaignId} person={person} />
        </li>
    );
}
