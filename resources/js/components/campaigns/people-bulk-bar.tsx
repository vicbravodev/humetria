import { Send } from 'lucide-react';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import { Button } from '@/components/ui/button';
import { peopleText, remindable } from '@/lib/evaluation';
import type { PeopleFilters } from '@/types';

/**
 * Over the list of pending people, or of those whose link failed: how many it holds and a reminder to all of them
 * (pantalla Eval-Activa). The table under it squares its top corners.
 */
export default function PeopleBulkBar({
    route,
    filters,
    count,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    filters: PeopleFilters;
    count: number;
}) {
    if (!remindable(filters.status, count)) {
        return null;
    }

    return (
        <div
            data-slot="bulk-bar"
            className="flex flex-wrap items-center gap-3 rounded-t-xl border border-b-0 bg-accent px-4 py-2.5"
        >
            <span className="grow text-sm font-medium">
                {peopleText(count)} en esta lista
            </span>
            <ResendFilteredDialog
                tenantSlug={route[0]}
                campaignId={route[1]}
                filters={filters}
                count={count}
                trigger={
                    <Button size="sm">
                        <Send aria-hidden="true" />
                        Enviar recordatorio
                    </Button>
                }
            />
        </div>
    );
}
