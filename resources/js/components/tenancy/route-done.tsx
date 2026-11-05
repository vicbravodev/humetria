import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import StageBar from '@/components/humetria/stage-bar';
import RouteCycle from '@/components/tenancy/route-cycle';
import RouteLive from '@/components/tenancy/route-live';
import { Button } from '@/components/ui/button';
import type { Norm } from '@/lib/compliance-routes';
import { LIVE } from '@/lib/route-links';
import type { NavItem, StageState } from '@/types';

/**
 * «Todo al día» (board V3-AlDia): the six stages done, the next obligation, the dossier to download and what stays alive.
 * NOM-035 adds its two-year cycle and the way to schedule the next evaluation.
 */
export default function RouteDone({
    norm,
    until,
    dossierHref,
    evaluationsHref,
}: {
    norm: Norm;
    until: string | null;
    dossierHref: NavItem['href'];
    evaluationsHref: NavItem['href'];
}) {
    return (
        <div className="flex flex-col gap-8">
            <header className="flex flex-col gap-4">
                <span className="inline-flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Check aria-hidden className="size-7" />
                </span>
                <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                    Todo al día en la {norm.title}
                </h1>
                <p className="max-w-[720px] text-lg leading-7 text-muted-foreground">
                    Terminaste las 6 etapas. Tu expediente está completo.
                    {until !== null &&
                        ` La siguiente evaluación toca antes del ${until}.`}
                </p>
                <StageBar
                    states={Array.from({ length: 6 }, (): StageState => 'done')}
                />
                <div className="flex flex-wrap gap-3">
                    <Button size="lg" asChild>
                        <Link href={dossierHref}>Descargar expediente</Link>
                    </Button>
                    {norm.key === 'nom035' && (
                        <Button size="lg" variant="outline" asChild>
                            <Link href={evaluationsHref}>
                                Programar la siguiente evaluación
                            </Link>
                        </Button>
                    )}
                </div>
            </header>
            <div className="grid items-start gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_380px]">
                <RouteLive items={LIVE[norm.key]} />
                {until !== null && <RouteCycle until={until} />}
            </div>
        </div>
    );
}
