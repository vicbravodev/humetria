import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type UrgencyItem = {
    key: string;
    /** What it belongs to: "NOM-037", "Quejas". */
    tag: string;
    title: string;
    detail: string;
    action: string;
    href: string;
};

/**
 * "Atiende primero" (board V2-Panel): what cannot wait, each with its one action, the first one the primary. Each row
 * puts its button below the text when its card is narrow, whatever the window.
 */
export default function UrgencyList({ items }: { items: UrgencyItem[] }) {
    return (
        <ul className="@container overflow-hidden rounded-xl border border-risk-alto bg-card">
            {items.map((item, index) => (
                <li
                    key={item.key}
                    className="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3.5 gap-y-2.5 border-t px-5 py-4 first:border-t-0 @[560px]:grid-cols-[auto_minmax(0,1fr)_auto] @[560px]:gap-4"
                >
                    <span className="inline-flex h-[26px] items-center rounded-md border bg-muted px-2 text-sm font-semibold">
                        {item.tag}
                    </span>
                    <div className="min-w-0">
                        <p className="font-medium">{item.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {item.detail}
                        </p>
                    </div>
                    <Button
                        asChild
                        variant={index === 0 ? 'default' : 'outline'}
                        className="col-start-2 justify-self-start @[560px]:col-start-auto"
                    >
                        <Link href={item.href}>{item.action}</Link>
                    </Button>
                </li>
            ))}
        </ul>
    );
}
