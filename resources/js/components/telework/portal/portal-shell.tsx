import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';

/**
 * The frame of the person's portal (design system: SurveyShell): whose company asks, that it is personal, and Humetría
 * only as the seal at the foot.
 */
export default function PortalShell({
    company,
    children,
}: {
    company: string | null;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-lg leading-7">
            <header className="flex h-[60px] shrink-0 items-center justify-between gap-3 border-b bg-card px-4">
                <p className="text-base font-semibold">
                    {company ?? 'Teletrabajo'}
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
