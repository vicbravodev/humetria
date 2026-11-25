import CompanyMark from '@/components/company-mark';
import { ArrowLeft, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';
import { Button } from '@/components/ui/button';

/**
 * The frame of the public pages of the channel (design system: SurveyShell, boards Queja*): whose company it is, that it is
 * confidential, and Humetría only as the seal at the foot. The header keeps the way back (`onBack`), the progress sticks
 * under it and the actions of the screen to the bottom, where the thumb is. No menu, no account, nothing that remembers who came.
 */
export default function PublicShell({
    company,
    onBack,
    progress,
    actions,
    children,
}: {
    company: string | null;
    /** The way back, in the header: «Atrás». */
    onBack?: () => void;
    /** What sticks under the header: «Paso N de 4» and its bar. */
    progress?: ReactNode;
    /** The buttons of the screen, stuck to the bottom: the main one first. */
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-lg leading-7">
            <header className="sticky top-0 z-10 flex h-[60px] shrink-0 items-center justify-between gap-3 border-b bg-card px-4">
                {onBack !== undefined && (
                    <Button
                        type="button"
                        variant="ghost"
                        className="-ml-3 text-primary"
                        onClick={onBack}
                    >
                        <ArrowLeft aria-hidden />
                        Atrás
                    </Button>
                )}
                <p
                    className={
                        onBack === undefined
                            ? 'text-base font-semibold'
                            : 'min-w-0 flex-1 truncate text-right text-base font-semibold'
                    }
                >
                    <CompanyMark
                        name={company ?? 'Quejas y denuncias'}
                        className="h-8"
                    />
                </p>
                <p className="flex shrink-0 items-center gap-1.5 text-base font-medium text-primary">
                    <Lock aria-hidden="true" className="size-4" />
                    <span className={onBack === undefined ? '' : 'sr-only'}>
                        Confidencial
                    </span>
                </p>
            </header>
            {progress !== undefined && (
                <div className="sticky top-[60px] z-10 border-b bg-background px-4 py-3">
                    <div className="mx-auto w-full max-w-xl">{progress}</div>
                </div>
            )}
            <main className="flex w-full flex-1 justify-center px-4 pt-6 pb-6 md:px-6 md:py-10">
                {children}
            </main>
            {actions !== undefined && (
                <div className="sticky bottom-0 z-10 border-t bg-background px-4 pt-3 pb-4 md:px-6">
                    <div className="mx-auto flex w-full max-w-xl flex-col gap-2.5">
                        {actions}
                    </div>
                </div>
            )}
            <footer className="flex justify-center px-4 py-3.5">
                <Logo kind="seal" className="h-4" />
            </footer>
        </div>
    );
}
