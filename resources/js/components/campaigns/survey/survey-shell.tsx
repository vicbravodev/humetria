import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';

/**
 * The frame of the survey (design system: SurveyShell): who asks, that it is confidential, and Humetría only as the
 * "hecho con humetría" seal at the foot.
 */
export default function SurveyShell({
    company,
    children,
}: {
    company: string | null;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <header className="flex items-center justify-between gap-4 border-b px-4 py-4 md:px-6">
                <p className="text-lg leading-7 font-semibold">
                    {company ?? 'Evaluación'}
                </p>
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Lock aria-hidden="true" className="size-4" />
                    Confidencial
                </p>
            </header>
            <main className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-8 px-4 py-8 md:px-6">
                {children}
            </main>
            <footer className="flex justify-center border-t px-4 py-4">
                <Logo kind="seal" className="h-5" />
            </footer>
        </div>
    );
}
