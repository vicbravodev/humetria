import { PortalTabBar } from 'humetria';
import type { ReactNode } from 'react';

Object.assign(globalThis, { HumetriaPage: { url: '/me/aceros-del-norte', props: { brand: null } } });

// `transform` makes the box the containing block of the fixed bar, so it floats at the foot of the card.
const Phone = ({ children }: { children: ReactNode }) => (
    <div className="relative w-full bg-background" style={{ height: 260, transform: 'translateZ(0)' }}>
        {children}
    </div>
);

export const StartWithPendingAndPunch = () => (
    <Phone>
        <PortalTabBar current="start" pending={3} punchUrl="/me/aceros-del-norte/punch" onSelect={() => {}} />
    </Phone>
);

export const StartWithoutAttendance = () => (
    <Phone>
        <PortalTabBar current="start" pending={1} punchUrl={null} onSelect={() => {}} />
    </Phone>
);

export const PendingOpen = () => (
    <Phone>
        <PortalTabBar current="pending" pending={3} punchUrl="/me/aceros-del-norte/punch" onSelect={() => {}} />
    </Phone>
);
