import type { ReactNode } from 'react';

/**
 * The frame of the page of the people (board V4-Pagina-Personas), on a phone first: whose company speaks with the way to
 * another center at its right, the page, and at its foot the seal and that nobody's visit is kept (criterio C-55).
 */
export default function WorkersShell({
    company,
    aside,
    children,
}: {
    company: string;
    /** What goes at the right of the bar: «Otro centro», when there is one. */
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-base leading-6">
            <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-card px-4 md:px-6">
                <p className="min-w-0 truncate text-sm font-semibold">
                    {company}
                </p>
                {aside}
            </header>
            <main className="flex w-full flex-1 justify-center px-4 pt-6 pb-4 md:px-6 md:py-10">
                {children}
            </main>
            <footer className="px-4 py-5 text-center text-caption text-muted-foreground">
                <p className="mb-2">
                    Sus trámites personales (vacaciones, confirmaciones) están
                    en el enlace personal que llegó a su correo.
                </p>
                hecho con{' '}
                <span className="font-semibold text-foreground">humetría</span>{' '}
                · no guardamos quién visita esta página
            </footer>
        </div>
    );
}
