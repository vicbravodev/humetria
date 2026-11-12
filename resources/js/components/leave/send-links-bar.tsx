import { router } from '@inertiajs/react';
import { useState } from 'react';
import BulkBar from '@/components/humetria/bulk-bar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { links } from '@/routes/tenant/vacations/balances';

/**
 * Who is ticked and the one thing to do with them: email each the link to their own vacations, where they read and confirm
 * their annual statement. It sticks to the bottom of the table only while someone is ticked.
 */
export default function SendLinksBar({
    chosen,
    onDone,
}: {
    chosen: string[];
    onDone: () => void;
}) {
    const tenantSlug = useTenantSlug();
    const [sending, setSending] = useState(false);

    return (
        <BulkBar count={chosen.length} onClear={onDone}>
            <Button
                disabled={sending}
                onClick={() =>
                    router.post(
                        links.url(tenantSlug),
                        { employees: chosen },
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                            onSuccess: onDone,
                        },
                    )
                }
            >
                {sending && <Spinner />}
                Enviar constancia para acuse
            </Button>
        </BulkBar>
    );
}
