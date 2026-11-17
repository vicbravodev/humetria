import type { ReactNode } from 'react';
import PersonFrame from '@/components/person-frame';
import type { PortalPrivacy } from '@/lib/seating/portal-privacy';
import { PORTAL_PRIVACY } from '@/lib/seating/portal-privacy';

/**
 * The frame of a Ley Silla screen of the person (findings 13 and 14): one task per screen, its action stuck to the bottom.
 * Who sees what it collects is always said: in the header of a centered screen, and as a chip on top of a task screen
 * (whose header carries «Atrás» instead).
 */
export default function PortalFrame({
    company,
    privacy,
    back,
    centered = false,
    actions,
    children,
}: {
    company: string;
    privacy: PortalPrivacy;
    back?: string;
    centered?: boolean;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const area = PORTAL_PRIVACY[privacy];

    return (
        <PersonFrame
            company={company}
            area={area}
            back={back}
            centered={centered}
            actions={actions}
        >
            <div className="flex w-full max-w-2xl flex-col gap-5">
                {back !== undefined && (
                    <p
                        data-test="portal-privacy"
                        className="flex items-center gap-1.5 self-start rounded-full bg-accent px-3 py-1 text-sm font-medium text-accent-foreground"
                    >
                        <area.icon aria-hidden className="size-4" />
                        {area.label}
                    </p>
                )}
                {children}
            </div>
        </PersonFrame>
    );
}
