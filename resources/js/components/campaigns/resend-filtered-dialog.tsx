import type { ReactElement } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { peopleQuery } from '@/lib/campaigns';
import { remindable } from '@/lib/evaluation';
import { countOf } from '@/lib/plural';
import { resend } from '@/routes/tenant/campaigns';
import type { PeopleFilters } from '@/types';

/**
 * Sends a new link to the people the filters show, after saying their old link stops working. Only while the list shows
 * pending people, or those whose link failed: then how many get one is exactly how many are listed.
 */
export default function ResendFilteredDialog({
    campaignId,
    filters,
    count,
    trigger,
}: {
    campaignId: string;
    filters: PeopleFilters;
    count: number;
    /** The button that opens it; "Reenviar a N personas" when not given. */
    trigger?: ReactElement;
}) {
    const tenantSlug = useTenantSlug();
    const people = countOf(count, 'persona', 'personas');

    if (!remindable(filters.status, count)) {
        return null;
    }

    return (
        <ConfirmDialog
            trigger={
                trigger ?? (
                    <Button variant="outline">Reenviar a {people}</Button>
                )
            }
            title="¿Reenviar el enlace?"
            description={`Se reenvía su enlace a ${people}. Es el mismo de antes: el que ya tienen sigue sirviendo.`}
            confirmLabel="Reenviar enlaces"
            destructive={false}
            form={resend.form([tenantSlug, campaignId])}
            transform={() => peopleQuery(filters)}
            preserveScroll
        />
    );
}
