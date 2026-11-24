import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import type { ReactNode } from 'react';
import { cspNonce } from '@/lib/csp-nonce';

/** What the header's menus need to animate: the motion features, loaded once, and the CSP nonce for the styles Motion injects. */
export default function MotionFrame({ children }: { children: ReactNode }) {
    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                {children}
            </MotionConfig>
        </LazyMotion>
    );
}
