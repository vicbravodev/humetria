import type { ResourceLink } from '@/types/public-nav';
import { cn } from '@/lib/utils';

/** One link of the menu «Recursos»: its icon (or Humi's own face), its name and what it is for. */
export default function ResourceItem({
    item,
    href,
    current,
    onPick,
}: {
    item: ResourceLink;
    href: string;
    current: boolean;
    onPick: () => void;
}) {
    return (
        <a
            href={href}
            data-test={item.test}
            onClick={onPick}
            aria-current={current ? 'page' : undefined}
            className={cn(
                'grid min-h-11 grid-cols-[40px_minmax(0,1fr)] items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-accent',
                current && 'bg-accent',
            )}
        >
            {item.image === undefined ? (
                <span className="grid size-10 place-items-center rounded-xl bg-accent text-primary">
                    <svg
                        aria-hidden
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.75}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="size-5"
                    >
                        {item.paths.map((path) => (
                            <path key={path} d={path} />
                        ))}
                    </svg>
                </span>
            ) : (
                <span className="grid size-10 place-items-center">
                    <img
                        src={`/brand/${item.image}.svg`}
                        alt=""
                        width={40}
                        height={40}
                        className="size-10 dark:hidden"
                    />
                    <img
                        src={`/brand/${item.image}-oscuro.svg`}
                        alt=""
                        width={40}
                        height={40}
                        className="hidden size-10 dark:block"
                    />
                </span>
            )}
            <span className="grid gap-0.5">
                <span
                    className={cn('font-semibold', current && 'text-primary')}
                >
                    {item.label}
                </span>
                <span className="text-caption text-muted-foreground">
                    {item.hint}
                </span>
            </span>
        </a>
    );
}
