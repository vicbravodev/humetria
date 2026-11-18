import { Lock } from 'lucide-react';
import CreatePulseButton from '@/components/engagement/pulse/create-pulse-button';
import TrendLink from '@/components/engagement/pulse/trend-link';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { scheduleBadge } from '@/lib/engagement/pulse';
import { anonymityText } from '@/lib/engagement/results';
import type { PulseModeOption, PulseSchedule } from '@/types';

/**
 * «Pulso» with how often it goes out, the promise of anonymity, «Crear un pulso ahora» when nothing is in progress and
 * «Ver tendencia» as the primary action once a survey closed.
 */
export default function PulseHomeHeader({
    schedule,
    createWith,
    hasHistory,
    minGroup,
}: {
    schedule: PulseSchedule | null;
    /** The modes to create a pulse in, when this person may create one now. */
    createWith: PulseModeOption[] | null;
    hasHistory: boolean;
    /** The company's minimum per group: what the next pulse will promise. */
    minGroup: number;
}) {
    return (
        <PageHeader
            title="Pulso"
            status={
                schedule !== null && (
                    <Badge variant={schedule.active ? 'default' : 'secondary'}>
                        {scheduleBadge(schedule)}
                    </Badge>
                )
            }
            facts={[
                {
                    icon: Lock,
                    text: anonymityText(minGroup),
                },
            ]}
        >
            {createWith !== null && <CreatePulseButton modes={createWith} />}
            {hasHistory && <TrendLink />}
        </PageHeader>
    );
}
