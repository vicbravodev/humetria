import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { suggestedAction } from '@/lib/billing-aging';
import { show } from '@/routes/billing/accounts';
import { store } from '@/routes/billing/reminders';
import type { AccountRow } from '@/types';

const LABELS = { collect: 'Cobrar', remind: 'Recordar', view: 'Ver' };

/**
 * The next step with an account, by how old its balance is: «Recordar» sends the reminder right there (for whoever may), the
 * rest open the account; nothing when it owes nothing.
 */
export default function AccountSuggestedAction({
    account,
    canRemind,
}: {
    account: AccountRow;
    canRemind: boolean;
}) {
    const action = suggestedAction(account.aging, canRemind);

    if (action === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = `${LABELS[action]}: ${account.legal_name}`;

    if (action === 'remind') {
        return (
            <Button
                size="sm"
                variant="outline"
                aria-label={label}
                onClick={() =>
                    router.post(
                        store.url(),
                        { account_ids: [account.id] },
                        { preserveScroll: true },
                    )
                }
            >
                {LABELS[action]}
            </Button>
        );
    }

    return (
        <Button size="sm" variant="outline" asChild>
            <Link href={show(account.id)} aria-label={label}>
                {LABELS[action]}
            </Link>
        </Button>
    );
}
