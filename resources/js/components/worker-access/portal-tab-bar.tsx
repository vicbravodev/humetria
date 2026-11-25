import { Link } from '@inertiajs/react';
import { Bell, Fingerprint, House, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import GlideChips from '@/components/humetria/glide-chips';
import PortalTabContent from '@/components/worker-access/portal-tab-content';
import { cspNonce } from '@/lib/csp-nonce';
import { cn } from '@/lib/utils';

export type PortalTab = 'start' | 'pending' | 'account';

/** `punch` is not a screen of the home: it is a link to Marcar, where the company has attendance. */
type BarTab = { label: string; icon: LucideIcon } & (
    | { key: PortalTab }
    | { key: 'punch' }
);

const TABS: BarTab[] = [
    { key: 'start', label: 'Inicio', icon: House },
    { key: 'punch', label: 'Marcar', icon: Fingerprint },
    { key: 'pending', label: 'Pendientes', icon: Bell },
    { key: 'account', label: 'Cuenta', icon: UserRound },
];

const TAB =
    'relative flex min-h-14 w-full flex-col items-center justify-center gap-0.5 rounded-full px-3 text-caption font-medium transition-colors duration-150 ease-out focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring';

/**
 * The tabs of the person's portal (finding 3): the frosted pill of RH's phone menu, with the chip that glides to the tab
 * open, and how many things wait on «Pendientes». Marcar, where the company has attendance, takes the person to its own
 * screen. Only on the portal's top-level screens, never on a task or on anything anonymous.
 */
export default function PortalTabBar({
    current,
    pending,
    punchUrl,
    onSelect,
}: {
    current: PortalTab;
    pending: number;
    /** Where Marcar is; null where the company has no attendance, and the tab is not there. */
    punchUrl: string | null;
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
                        const content = (
                            <PortalTabContent
                                icon={tab.icon}
                                label={tab.label}
                                badge={tab.key === 'pending' ? pending : 0}
                                here={here}
                            />
                        );

                        if (tab.key === 'punch') {
                            return punchUrl === null ? null : (
                                <div
                                    key="punch"
                                    className="relative flex flex-1"
                                >
                                    <Link
                                        href={punchUrl}
                                        className={cn(TAB, 'text-foreground')}
                                    >
                                        {content}
                                    </Link>
                                </div>
                            );
                        }

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
                                    {content}
                                </button>
                            </div>
                        );
                    })}
                </nav>
            </MotionConfig>
        </LazyMotion>
    );
}
