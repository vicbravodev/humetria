import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { suggestedAction } from '@/lib/billing-aging';
import { show } from '@/routes/billing/accounts';
import type { AccountRow } from '@/types';

const LABELS = { collect: 'Cobrar', remind: 'Recordar', view: 'Ver' };

/** The next step with an account, by how old its balance is; nothing when it owes nothing. */
export default function AccountSuggestedAction({
    account,
}: {
    account: AccountRow;
}) {
    const action = suggestedAction(account.aging);

    if (action === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <Button
            size="sm"
            variant={action === 'view' ? 'ghost' : 'outline'}
            asChild
        >
            <Link
                href={show(account.id)}
                aria-label={`${LABELS[action]}: ${account.legal_name}`}
            >
                {LABELS[action]}
            </Link>
        </Button>
    );
}
