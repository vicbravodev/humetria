import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import Initials from '@/components/humetria/initials';
import Logo from '@/components/humetria/logo';

/**
 * The frame of the manager's feedback in their portal (design system: SurveyShell): whose company it is, that the link is
 * personal, and Humetría only as the seal at the foot.
 */
export default function PortalShell({
    company,
    children,
}: {
    company: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-lg leading-7">
            <header className="flex h-[60px] shrink-0 items-center justify-between gap-3 border-b bg-card px-4">
                <p className="flex min-w-0 items-center gap-3 text-base font-semibold">
                    <Initials name={company} kind="company" />
                    {company}
                </p>
                <p className="flex items-center gap-1.5 text-base font-medium text-primary">
                    <Lock aria-hidden="true" className="size-4" />
                    Personal
                </p>
            </header>
            <main className="flex w-full flex-1 justify-center px-4 pt-6 pb-4 md:px-6 md:py-10">
                {children}
            </main>
            <footer className="flex justify-center px-4 py-3.5">
                <Logo kind="seal" className="h-4" />
            </footer>
        </div>
    );
}
