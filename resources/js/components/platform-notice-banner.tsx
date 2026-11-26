import { usePage } from '@inertiajs/react';
import PlatformNoticeCard from '@/components/platform-notice-card';

/**
 * What Humetría tells the company now (a novelty, a maintenance, an incident, a billing matter), on every page of its panel. A
 * person may close it; it comes back only if the announcement changes. A notice that cannot be closed has no button.
 */
export function PlatformNoticeBanner() {
    const { platform_notice: notice } = usePage().props;

    if (!notice) {
        return null;
    }

    // Keyed by the notice, so another one starts open.
    return <PlatformNoticeCard key={notice.id} notice={notice} />;
}
