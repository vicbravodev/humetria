import { Link } from '@inertiajs/react';
import { CircleAlert, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AttentionItem } from '@/lib/attendance/attention';
import { cn } from '@/lib/utils';

/** One thing that needs RH: what it is, why in a line and the button that settles it; the first one gets the primary one. */
export default function AttentionRow({
    item,
    first,
}: {
    item: AttentionItem;
    first: boolean;
}) {
    const Icon = item.tone === 'alert' ? CircleAlert : TriangleAlert;
    const variant = first ? 'default' : 'outline';

    return (
        <li className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4 px-5 py-4 max-sm:grid-cols-[auto_minmax(0,1fr)]">
            <span
                aria-hidden
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                    item.tone === 'alert'
                        ? 'bg-risk-alto-bg text-risk-alto-fg'
                        : 'bg-warning text-warning-foreground',
                )}
            >
                <Icon className="size-5" />
            </span>
            <div className="flex min-w-0 flex-col gap-0.5">
                <h3 className="font-semibold text-balance">{item.title}</h3>
                <p className="text-sm text-pretty text-muted-foreground">
                    {item.text}
                </p>
            </div>
            <Button asChild variant={variant} className="max-sm:col-span-full">
                {item.href.startsWith('#') ? (
                    <a href={item.href}>{item.action}</a>
                ) : (
                    <Link href={item.href}>{item.action}</Link>
                )}
            </Button>
        </li>
    );
}
