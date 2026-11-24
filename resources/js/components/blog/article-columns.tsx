import type { ReactNode } from 'react';

/** The table of contents beside the body on a wide screen, above it on a phone. */
export default function ArticleColumns({ children }: { children: ReactNode }) {
    return (
        <div className="mx-auto mt-10 grid w-full max-w-[1000px] gap-8 px-5 sm:px-8 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-14">
            {children}
        </div>
    );
}
