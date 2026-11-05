import { Link } from '@inertiajs/react';
import { CheckCheck, Printer, Smartphone } from 'lucide-react';
import ReachCard from '@/components/disclosure/reach-card';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dissemination, page } from '@/routes/tenant/disclosure';
import { poster } from '@/routes/tenant/disclosure/page';
import type { DisclosureIndexProps } from '@/types';

/**
 * How it reaches the people (board V4-Difusion): their page, the poster that leads to it and who confirmed the policy. The link
 * of the page is a credential: it is seen, and audited, on its own page.
 */
export default function MatrixReach({
    reach,
    manage,
}: {
    reach: DisclosureIndexProps['reach'];
    manage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const percent =
        reach.people === 0
            ? 0
            : Math.round((reach.confirmed / reach.people) * 100);

    return (
        <div className="grid gap-5 md:grid-cols-3">
            <ReachCard
                icon={Smartphone}
                title="Página de las personas"
                actions={
                    manage && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={page(tenantSlug)}>
                                {reach.page
                                    ? 'Ver el enlace y su código'
                                    : 'Activar la página'}
                            </Link>
                        </Button>
                    )
                }
            >
                Una por centro. No pide cuenta ni guarda quién entra.{' '}
                {reach.page ? 'Está activa.' : 'Aún no está activa.'}
            </ReachCard>
            <ReachCard
                icon={Printer}
                title="Cartel"
                actions={
                    manage &&
                    reach.page && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={poster(tenantSlug)}>
                                Imprimir el cartel
                            </Link>
                        </Button>
                    )
                }
            >
                Un código que lleva a la página. Pégalo junto al checador o en
                el comedor y regístralo como medio de difusión.
            </ReachCard>
            <ReachCard
                icon={CheckCheck}
                title="Quién confirmó que la leyó"
                actions={
                    manage && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={dissemination(tenantSlug)}>
                                Ver detalle
                            </Link>
                        </Button>
                    )
                }
            >
                {reach.people === 0 ? (
                    'Cuando publiques la política, aquí verás cuántas personas confirmaron que la leyeron.'
                ) : (
                    <span className="flex flex-col gap-3">
                        <span className="flex items-baseline gap-2">
                            <span className="text-3xl font-semibold text-foreground">
                                {reach.confirmed}
                            </span>
                            de {reach.people} personas · {percent} %
                        </span>
                        <ProgressBar
                            value={reach.confirmed}
                            max={reach.people}
                            label="Personas que confirmaron que leyeron la política"
                        />
                    </span>
                )}
            </ReachCard>
        </div>
    );
}
