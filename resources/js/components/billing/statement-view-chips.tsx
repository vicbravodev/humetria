import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/billing/accounts';

const VIEWS: { value: 'all' | 'open'; label: string }[] = [
    { value: 'all', label: 'Todos' },
    { value: 'open', label: 'Abiertos' },
];

/** «Todos · Abiertos»: every statement of the account, or only the ones still owing; each a link, so the server pages it. */
export default function StatementViewChips({
    accountId,
    view,
}: {
    accountId: string;
    view: 'all' | 'open';
}) {
    return (
        <nav aria-label="Qué estados ver" className="flex gap-2">
            {VIEWS.map((option) => (
                <Link
                    key={option.value}
                    href={show.url(accountId, {
                        query:
                            option.value === 'all'
                                ? {}
                                : { statements: 'open' },
                    })}
                    preserveScroll
                    aria-current={view === option.value ? 'page' : undefined}
                    className={cn(
                        'touch-hit inline-flex h-9 items-center rounded-lg border border-input px-3 text-sm font-medium',
                        view === option.value &&
                            'border-primary bg-accent text-accent-foreground',
                    )}
                >
                    {option.label}
                </Link>
            ))}
        </nav>
    );
}
