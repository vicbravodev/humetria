import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';
import { cn } from '@/lib/utils';

/**
 * The frame of the pages a person of the company opens for themselves, on a phone first (design system: SurveyShell,
 * boards P1 to P4): whose company speaks, what the page is (`area`: the time record, their portal), the actions of the
 * screen stuck to the bottom where the thumb is, and Humetría only as the seal at the foot.
 *
 * A screen of state (a link that no longer works, a key that is done) is `centered`: its group sits in the middle of the free
 * space, a little above it, instead of at the top over half a screen of nothing. A task screen carries `back` in place of
 * the area; only the top-level screens of the portal carry the `bar` of tabs, never both bars at once.
 */
export default function PersonFrame({
    company,
    area,
    actions,
    centered = false,
    back,
    bar,
    children,
}: {
    company: string;
    /** What these pages are, beside the company: its icon and its name. */
    area: { icon: LucideIcon; label: string };
    /** The buttons of the screen, stuck to the bottom: the main one first. */
    actions?: ReactNode;
    /** The content is a state (icon, title, text): centered in the free space. */
    centered?: boolean;
    /** «Atrás» at the top left of a task screen, where it goes. */
    back?: string | undefined;
    /** The tabs of the portal, floating at the bottom (top-level screens only). */
    bar?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex min-h-dvh flex-col bg-background text-lg leading-7',
                bar !== undefined && 'pb-(--tab-bar-clearance)',
            )}
        >
            <header className="flex h-[60px] shrink-0 items-center justify-between gap-3 border-b bg-card px-4">
                {back !== undefined && (
                    <Link
                        href={back}
                        className="-ml-2 flex min-h-11 shrink-0 items-center gap-1 rounded-md pr-3 pl-1 text-base font-medium text-primary focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <ChevronLeft aria-hidden className="size-5" />
                        Atrás
                    </Link>
                )}
                <p className="min-w-0 truncate text-base font-semibold">
                    {company}
                </p>
                {back === undefined && (
                    <p className="flex shrink-0 items-center gap-1.5 text-base font-medium text-primary">
                        <area.icon aria-hidden="true" className="size-4" />
                        {area.label}
                    </p>
                )}
            </header>
            <main
                className={cn(
                    'flex w-full flex-1 justify-center px-4 pt-6 pb-6 md:px-6 md:py-10',
                    centered && 'items-center pb-[12dvh] md:pb-[12dvh]',
                )}
            >
                {children}
            </main>
            {actions !== undefined && (
                <div className="sticky bottom-0 z-10 border-t bg-background px-4 pt-3 pb-4 md:px-6">
                    <div className="mx-auto flex w-full max-w-md flex-col gap-2.5">
                        {actions}
                    </div>
                </div>
            )}
            <footer className="flex justify-center px-4 py-3.5">
                <Logo kind="seal" className="h-4" />
            </footer>
            {bar}
        </div>
    );
}
