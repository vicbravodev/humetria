/** Older and newer pages of the list, as plain links a crawler follows (`rel`). */
export default function BlogPagination({
    previous,
    next,
}: {
    previous: string | null;
    next: string | null;
}) {
    if (previous === null && next === null) {
        return null;
    }

    const LINK =
        'inline-flex min-h-11 items-center rounded-full border px-5 text-meta hover:bg-accent';

    return (
        <nav aria-label="Páginas" className="mt-10 flex justify-between gap-4">
            {next === null ? (
                <span />
            ) : (
                <a rel="next" href={next} className={LINK}>
                    Anteriores
                </a>
            )}
            {previous !== null && (
                <a rel="prev" href={previous} className={LINK}>
                    Siguientes
                </a>
            )}
        </nav>
    );
}
