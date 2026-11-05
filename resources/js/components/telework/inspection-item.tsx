import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { InspectionItem as Item } from '@/types';

/** One thing the inspector would ask for: shown with a check, or missing with a dashed ring, and its one button. */
export default function InspectionItem({
    item,
    primary,
}: {
    item: Item;
    primary: boolean;
}) {
    const ready = item.state === 'done';

    return (
        <li className="grid grid-cols-[28px_minmax(0,1fr)] items-center gap-x-3.5 gap-y-2.5 border-t px-5 py-4 @[560px]:grid-cols-[28px_minmax(0,1fr)_auto]">
            {ready ? (
                <span className="inline-flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Check aria-label="Lo tienes" className="size-4" />
                </span>
            ) : (
                <span
                    role="img"
                    aria-label="Falta"
                    className="size-7 rounded-full border-[1.5px] border-dashed border-risk-alto"
                />
            )}
            <div className="min-w-0">
                <p className="font-medium">{item.title}</p>
                <p className="text-sm text-muted-foreground">
                    {item.detail === null
                        ? item.numeral
                        : `${item.detail} · ${item.numeral}`}
                </p>
            </div>
            {item.href !== null && (
                <Button
                    asChild
                    variant={ready ? 'ghost' : primary ? 'default' : 'outline'}
                    className={cn(
                        'col-start-2 justify-self-start @[560px]:col-start-auto',
                        ready && 'text-primary',
                    )}
                >
                    <Link href={item.href}>
                        {ready ? 'Ver' : (item.action ?? 'Resolver')}
                        <span className="sr-only">: {item.title}</span>
                    </Link>
                </Button>
            )}
        </li>
    );
}
