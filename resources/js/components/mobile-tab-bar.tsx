import { usePage } from '@inertiajs/react';
import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import GlideChips from '@/components/humetria/glide-chips';
import { MobileTabControl } from '@/components/mobile-tab-control';
import { useSidebar } from '@/components/ui/sidebar';
import { useNavTenant } from '@/hooks/use-nav-tenant';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cspNonce } from '@/lib/csp-nonce';
import { currentTab, mobileTabs } from '@/lib/mobile-tabs';

/**
 * The phone's menu (board 40, V4-Menu-Roles): a bar of frosted glass floating at the bottom with Panel, Normas, Quejas
 * and Más, as the role's sidebar has them. Normas opens the standards of "Cumple la norma"; Más opens the whole menu, so
 * the top bar carries no menu button on a phone. A chip in the brand color sits under the tab of where the person is and
 * glides to the next one, as the landing's sections do.
 */
export function MobileTabBar() {
    const { auth, inCourse } = usePage().props;
    const tenant = useNavTenant();
    const { setOpenMobile } = useSidebar();
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const tabs = mobileTabs(tenant, auth.isStaff, inCourse?.count);
    const here = currentTab(
        tabs,
        (url) => isCurrentUrl(url),
        (url) => isCurrentOrParentUrl(url),
    );

    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                <nav
                    aria-label="Menú principal"
                    className="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 grid auto-cols-fr grid-flow-col gap-1 rounded-full border bg-card/85 p-1 shadow-raised backdrop-blur-xl backdrop-saturate-150 [view-transition-name:tab-bar] md:hidden print:hidden"
                >
                    {tabs.map((tab) => (
                        <div key={tab.key} className="relative flex">
                            <GlideChips
                                group="tab-bar"
                                current={here === tab.key}
                                hovered={false}
                                className="rounded-full"
                            />
                            <MobileTabControl
                                tab={tab}
                                current={here === tab.key}
                                openMenu={() => setOpenMobile(true)}
                            />
                        </div>
                    ))}
                </nav>
            </MotionConfig>
        </LazyMotion>
    );
}
