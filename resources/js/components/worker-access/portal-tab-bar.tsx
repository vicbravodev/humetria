import { Bell, House, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import GlideChips from '@/components/humetria/glide-chips';
import { cspNonce } from '@/lib/csp-nonce';
import { cn } from '@/lib/utils';

export type PortalTab = 'start' | 'pending' | 'account';

const TABS: { key: PortalTab; label: string; icon: LucideIcon }[] = [
    { key: 'start', label: 'Inicio', icon: House },
    { key: 'pending', label: 'Pendientes', icon: Bell },
    { key: 'account', label: 'Cuenta', icon: UserRound },
];

const TAB =
    'relative flex min-h-14 w-full flex-col items-center justify-center gap-0.5 rounded-full px-3 text-caption font-medium transition-colors duration-150 ease-out focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring';

/**
 * The tabs of the person's portal (finding 3): the frosted pill of RH's phone menu, with the chip that glides to the tab
 * open, and how many things wait on «Pendientes». Only on the portal's top-level screens, never on a task or on anything
 * anonymous.
 */
export default function PortalTabBar({
    current,
    pending,
    onSelect,
}: {
    current: PortalTab;
    pending: number;
    onSelect: (tab: PortalTab) => void;
}) {
    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                <nav
                    aria-label="Secciones del portal"
                    className="fixed inset-x-3 bottom-(--tab-bar-inset) z-30 mx-auto flex max-w-md gap-1 rounded-full border bg-card/85 p-1 shadow-raised backdrop-blur-xl backdrop-saturate-150 print:hidden"
                >
                    {TABS.map((tab) => {
                        const here = tab.key === current;

                        return (
                            <div key={tab.key} className="relative flex flex-1">
                                <GlideChips
                                    group="portal-tabs"
                                    current={here}
                                    hovered={false}
                                    className="rounded-full"
                                />
                                <button
                                    type="button"
                                    aria-current={here ? 'page' : undefined}
                                    onClick={() => onSelect(tab.key)}
                                    className={cn(
                                        TAB,
                                        here
                                            ? 'text-primary-foreground'
                                            : 'text-foreground',
                                    )}
                                >
                                    <span className="relative flex">
                                        <tab.icon
                                            aria-hidden
                                            className="size-5"
                                        />
                                        {tab.key === 'pending' &&
                                            pending > 0 && (
                                                <span
                                                    className={cn(
                                                        'absolute -top-2 -right-3.5 min-w-5 rounded-full px-1.5 text-center text-caption leading-5 font-semibold',
                                                        here
                                                            ? 'bg-primary-foreground text-primary'
                                                            : 'bg-primary text-primary-foreground',
                                                    )}
                                                >
                                                    {pending}
                                                    <span className="sr-only">
                                                        {pending === 1
                                                            ? ' pendiente'
                                                            : ' pendientes'}
                                                    </span>
                                                </span>
                                            )}
                                    </span>
                                    {tab.label}
                                </button>
                            </div>
                        );
                    })}
                </nav>
            </MotionConfig>
        </LazyMotion>
    );
}
