import { Link, usePage } from '@inertiajs/react';
import ArcMeter from '@/components/humetria/arc-meter';
import { Button } from '@/components/ui/button';
import {
    headcountText,
    pendingText,
    progressText,
} from '@/lib/telework/center';
import { center } from '@/routes/tenant/telework';
import type { CenterCard as Card } from '@/types';

/** A work center on the NOM-037 list (pantalla NOM035): its people, how many rows of table 10.3 it meets and what waits. */
export default function CenterCard({ card }: { card: Card }) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    const progress = progressText(card);

    return (
        <article className="flex flex-col gap-[18px] rounded-xl border bg-card p-[22px]">
            <div className="flex min-w-0 flex-col gap-0.5">
                <h3 className="text-lg leading-[26px] font-semibold">
                    {card.name}
                </h3>
                <p className="text-sm text-muted-foreground">{card.activity}</p>
            </div>
            <dl className="grid grid-cols-2 gap-3 border-b pb-[18px]">
                <div className="flex flex-col gap-0.5">
                    <dt className="text-[13px] leading-[18px] text-muted-foreground">
                        Teletrabajo
                    </dt>
                    <dd className="font-medium">
                        {headcountText(card.teleworkers, card.aspirants)}
                    </dd>
                </div>
                <div className="flex flex-col gap-0.5">
                    <dt className="text-[13px] leading-[18px] text-muted-foreground">
                        Comisión
                    </dt>
                    <dd className="font-medium">
                        {pendingText(card.pendingReviews)}
                    </dd>
                </div>
            </dl>
            <div className="flex flex-wrap items-center gap-4">
                <ArcMeter
                    value={card.done}
                    max={card.applicable}
                    label={`${progress} de la tabla 10.3 cumplidos`}
                    size="sm"
                />
                <div className="flex min-w-0 grow flex-col gap-0.5">
                    <p className="text-sm">
                        <span className="font-semibold tabular-nums">
                            {progress}
                        </span>
                    </p>
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        de la tabla de inspección
                    </p>
                </div>
                <Button size="sm" asChild>
                    <Link href={center([tenantSlug, card.id])}>
                        Ver tablero
                        <span className="sr-only"> de {card.name}</span>
                    </Link>
                </Button>
            </div>
        </article>
    );
}
