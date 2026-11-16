import { Link } from '@inertiajs/react';
import { KeyRound, Link2, Mail } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as access } from '@/routes/tenant/worker-access';

/**
 * How the person reaches their portal. With an email, the address of their telework section went there and they enter with
 * it; without one (or to start over) RH invites them, or reissues their access, in «Accesos».
 */
export default function PortalLinkPanel({
    name,
    hasEmail,
}: {
    name: string;
    hasEmail: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section className="flex flex-wrap items-center justify-between gap-3 card p-5">
            <div className="flex items-center gap-3">
                <IconTile icon={hasEmail ? Mail : Link2} />
                <div>
                    <h2 className="text-base font-semibold">
                        Acceso a su portal
                    </h2>
                    <p className="text-meta text-muted-foreground">
                        {hasEmail
                            ? 'Se le avisó por correo al registrar a esta persona.'
                            : 'Sin correo'}
                    </p>
                </div>
            </div>
            <Button variant="outline" asChild>
                <Link href={access.url(tenantSlug, { query: { q: name } })}>
                    <KeyRound aria-hidden />
                    Ver su acceso
                </Link>
            </Button>
        </section>
    );
}
