import type { BlogHeading } from '@/types/blog';

/** The h2 of the article to jump to: a sticky list beside it on a wide screen, a closed disclosure above it on a phone. */
export default function ArticleToc({ headings }: { headings: BlogHeading[] }) {
    if (headings.length < 2) {
        return null;
    }

    return (
        <aside className="lg:order-first">
            <details className="card p-4 lg:hidden">
                <summary className="flex min-h-11 cursor-pointer items-center text-meta font-semibold">
                    En este artículo
                </summary>
                <ol className="mt-3 grid gap-1 text-meta">
                    {headings.map((heading) => (
                        <li key={heading.id}>
                            <a
                                href={`#${heading.id}`}
                                className="inline-flex min-h-11 items-center text-muted-foreground hover:text-foreground"
                            >
                                {heading.text}
                            </a>
                        </li>
                    ))}
                </ol>
            </details>
            <nav
                aria-label="Contenido"
                className="sticky top-24 hidden lg:block"
            >
                <p className="text-caption font-semibold tracking-wide text-muted-foreground uppercase">
                    En este artículo
                </p>
                <ol className="mt-3 grid gap-0.5 border-s text-meta">
                    {headings.map((heading) => (
                        <li key={heading.id}>
                            <a
                                href={`#${heading.id}`}
                                className="-ms-px block border-s border-transparent py-1.5 ps-3 text-muted-foreground transition-colors hover:border-family hover:text-foreground"
                            >
                                {heading.text}
                            </a>
                        </li>
                    ))}
                </ol>
            </nav>
        </aside>
    );
}
