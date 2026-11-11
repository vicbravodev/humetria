import { router } from '@inertiajs/react';
import { RotateCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { retry } from '@/routes/tenant/climate/summaries';

/** Tries again a summary that failed: the only case in which one is made twice. */
export default function RetrySummaryButton({
    summaryId,
}: {
    summaryId: string;
}) {
    const tenantSlug = useTenantSlug();
    const [sending, setSending] = useState(false);

    return (
        <Button
            variant="outline"
            disabled={sending}
            onClick={() =>
                router.post(
                    retry.url([tenantSlug, summaryId]),
                    {},
                    {
                        preserveScroll: true,
                        onStart: () => setSending(true),
                        onFinish: () => setSending(false),
                    },
                )
            }
        >
            {sending ? <Spinner /> : <RotateCw aria-hidden />}
            Reintentar el resumen
        </Button>
    );
}
