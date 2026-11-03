import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center } from '@/routes/tenant/seating';
import type { SeatingCenterCard } from '@/types';

/** A work center on the Ley Silla list (pantalla NOM035): what it does, the sector it declared and the way in. */
export default function CenterCard({ card }: { card: SeatingCenterCard }) {
    const tenantSlug = useTenantSlug();

    return (
        <article className="flex flex-col gap-[18px] card p-[22px]">
            <div className="flex min-w-0 flex-col gap-0.5">
                <h3 className="text-lg leading-[26px] font-semibold">
                    {card.name}
                </h3>
                <p className="text-sm text-muted-foreground">{card.activity}</p>
            </div>
            <dl className="border-b pb-[18px]">
                <div className="flex flex-col gap-0.5">
                    <dt className="text-caption text-muted-foreground">
                        Sector declarado
                    </dt>
                    <dd className="font-medium">
                        {card.sectorLabel ?? 'Sin declarar'}
                    </dd>
                </div>
            </dl>
            <div className="flex justify-end">
                <Button size="sm" asChild>
                    <Link href={center([tenantSlug, card.id])}>
                        Abrir centro
                        <span className="sr-only"> {card.name}</span>
                    </Link>
                </Button>
            </div>
        </article>
    );
}
