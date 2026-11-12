import { Head, setLayoutProps } from '@inertiajs/react';
import ClimateHome from '@/components/engagement/climate-home';
import PulseHome from '@/components/engagement/pulse/pulse-home';
import {
    useEngagementKind,
    useEngagementName,
    useEngagementTrail,
} from '@/hooks/use-engagement-routes';
import type { EngagementHome } from '@/types';

/** The home of Clima or Pulso: only counts, RH never sees who answered. */
export default function EngagementIndex(props: EngagementHome) {
    const name = useEngagementName();

    setLayoutProps({ breadcrumbs: useEngagementTrail() });

    return (
        <>
            <Head title={name} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                {useEngagementKind() === 'pulse' ? (
                    <PulseHome {...props} />
                ) : (
                    <ClimateHome {...props} />
                )}
            </div>
        </>
    );
}
