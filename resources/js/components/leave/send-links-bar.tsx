import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { links } from '@/routes/tenant/vacations/balances';

/** Who is ticked and the one thing to do with them: email them the link to their own vacations. */
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
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-accent px-5 py-3"
        >
            <span className="font-medium">
                {countOf(chosen.length, 'persona elegida', 'personas elegidas')}
            </span>
            <div className="flex flex-wrap gap-2">
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
                    Enviar enlace
                </Button>
                <Button variant="outline" onClick={onDone}>
                    Quitar selección
                </Button>
            </div>
        </div>
    );
}
