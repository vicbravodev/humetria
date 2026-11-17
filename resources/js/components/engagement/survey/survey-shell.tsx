import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';

/**
 * The frame of the survey (design system: SurveyShell): who asks, that it is confidential, and Humetría only as the
 * "hecho con humetría" seal at the foot.
 */
export default function SurveyShell({
    company,
    label = 'Confidencial',
    children,
}: {
    company: string | null;
    /** What the badge at the right says: the survey is confidential; none on a page of group results, where it would say nothing. */
    label?: string | null;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-lg leading-7">
            <header className="flex h-[60px] shrink-0 items-center justify-between gap-3 border-b bg-card px-4">
                <p className="min-w-0 truncate text-base font-semibold">
                    {company ?? 'Encuesta'}
                </p>
                {label !== null && (
                    <p className="flex shrink-0 items-center gap-1.5 text-base font-medium text-primary">
                        <Lock aria-hidden="true" className="size-4" />
                        {label}
                    </p>
                )}
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
