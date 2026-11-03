import { Stethoscope } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import ReferralForm from '@/components/seating/referral-form';
import type { Referral } from '@/types';

/** The referrals to medical attention of the person (Quinta J) and the form to record one, or «evaluated, no referral». */
export default function ReferralsSection({
    assessmentId,
    referrals,
    canRefer,
}: {
    assessmentId: string;
    referrals: Referral[];
    canRefer: boolean;
}) {
    return (
        <SettingRow
            icon={Stethoscope}
            title="Canalización"
            description="Si hay signos o síntomas relacionados con trabajar de pie, queda registrado que se canalizó a atención médica. Sin detalle clínico."
        >
            {referrals.length > 0 && (
                <ul className="grid gap-2">
                    {referrals.map((referral) => (
                        <li
                            key={referral.id}
                            className="rounded-lg border px-4 py-3"
                        >
                            <p className="font-medium">
                                {referral.referred
                                    ? 'Se canalizó a atención médica'
                                    : 'Evaluada, sin canalización'}
                                <span className="ml-2 text-caption font-normal text-muted-foreground">
                                    {referral.referredOn}
                                </span>
                            </p>
                            {referral.note !== null && (
                                <p className="text-sm text-muted-foreground">
                                    {referral.note}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {canRefer && <ReferralForm assessmentId={assessmentId} />}
        </SettingRow>
    );
}
