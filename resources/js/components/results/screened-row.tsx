import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import { referralChip } from '@/lib/referral';
import { cn } from '@/lib/utils';
import type { ScreenedPerson } from '@/types';

/** One person of the list (board V4-N035-Canalizacion): who they are and where their channeling stands; it opens them. */
export default function ScreenedRow({
    person,
    href,
    open,
}: {
    person: ScreenedPerson;
    href: string;
    open: boolean;
}) {
    const chip = referralChip(person.referral);

    return (
        <li>
            <Link
                href={href}
                preserveScroll
                preserveState
                aria-current={open ? 'true' : undefined}
                className={cn(
                    'grid min-h-[68px] grid-cols-[32px_minmax(0,1fr)_auto] items-center gap-3 border-t px-[18px] py-3 hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                    open && 'bg-accent shadow-[inset_3px_0_0_var(--primary)]',
                )}
            >
                <Initials name={person.name} className="size-8 text-caption" />
                <span className="min-w-0">
                    <span className="block font-medium">{person.name}</span>
                    <span className="block text-sm text-muted-foreground">
                        {[person.jobTitle, person.area]
                            .filter((part) => part !== '')
                            .join(' · ')}
                    </span>
                </span>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-0.5 text-caption font-medium whitespace-nowrap',
                        chip.tone === 'pending'
                            ? 'bg-risk-medio-bg text-risk-medio-fg'
                            : 'bg-accent text-primary',
                    )}
                >
                    {chip.label}
                </span>
            </Link>
        </li>
    );
}
