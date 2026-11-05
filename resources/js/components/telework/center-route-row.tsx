import { Link } from '@inertiajs/react';
import StageBar from '@/components/humetria/stage-bar';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { stageStates } from '@/lib/dashboard';
import { nextInCenter, stagesDone } from '@/lib/telework/centers';
import { certificate, route } from '@/routes/tenant/telework';
import { show as applies } from '@/routes/tenant/telework/applies';
import type { CenterCard } from '@/types';

/** A center in «En cada centro» (board V2-Centros-N037): its people, where it stands, what comes next and its route. */
export default function CenterRouteRow({ card }: { card: CenterCard }) {
    const tenantSlug = useTenantSlug();
    const at: [string, string] = [tenantSlug, card.id];
    const [href, action] = card.route.applies
        ? [route(at), 'Abrir ruta']
        : card.route.answered
          ? [certificate(at), 'Ver constancia']
          : [applies(at), 'Contestar'];

    return (
        <li className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 border-t px-5 py-4 @[860px]:grid-cols-[minmax(160px,1.3fr)_80px_minmax(150px,1fr)_minmax(0,1.6fr)_140px]">
            <div className="min-w-0">
                <p className="font-medium">{card.name}</p>
                <p className="text-sm text-muted-foreground">{card.activity}</p>
            </div>
            <p className="text-sm @[860px]:text-base">
                <span className="@[860px]:sr-only">Personas: </span>
                {card.teleworkers}
            </p>
            <div className="col-span-2 flex flex-col gap-1.5 @[860px]:col-span-1">
                {card.route.applies ? (
                    <StageBar states={stageStates(card.route)} />
                ) : null}
                <span className="text-sm text-muted-foreground">
                    {card.route.applies
                        ? stagesDone(card)
                        : card.route.answered
                          ? 'No aplica'
                          : 'Falta contestar'}
                </span>
            </div>
            <p className="col-span-2 text-sm @[860px]:col-span-1">
                {nextInCenter(card)}
            </p>
            <Button
                asChild
                variant="outline"
                className="col-span-2 justify-self-start @[860px]:col-span-1"
            >
                <Link href={href}>
                    {action}
                    <span className="sr-only"> de {card.name}</span>
                </Link>
            </Button>
        </li>
    );
}
