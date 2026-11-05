import { Link } from '@inertiajs/react';
import { FileText, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { evidence } from '@/routes/tenant/complaints';

/** «Si llega una inspección» (board V4-Quejas-Canal): the evidence that the channel exists and how it works, never a case. */
export default function ChannelInspection() {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="inspection"
            className="flex flex-col gap-3 card p-6"
        >
            <h2
                id="inspection"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <ShieldCheck aria-hidden className="size-5" />
                Si llega una inspección
            </h2>
            <p className="text-sm text-muted-foreground">
                La evidencia muestra que el canal existe, cómo funciona y quién
                atiende. Nunca incluye el contenido de un caso.
            </p>
            <Button variant="outline" className="self-start" asChild>
                <Link href={evidence(tenantSlug)}>
                    <FileText aria-hidden />
                    Ver la evidencia
                </Link>
            </Button>
        </section>
    );
}
