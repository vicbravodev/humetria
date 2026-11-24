import { ChevronRight } from 'lucide-react';
import { FAMILY_CLASS } from '@/lib/blog-family';
import { cn } from '@/lib/utils';
import type { BlogArticleData } from '@/types/blog';

const CRUMB =
    'inline-flex min-h-11 items-center rounded-sm hover:text-foreground';

/** Where the article sits, its tool, title, what it covers, when it was read against the law, and its cover. */
export default function ArticleHeader({
    article,
}: {
    article: BlogArticleData;
}) {
    return (
        <header
            className={cn(
                FAMILY_CLASS[article.family],
                'mx-auto w-full max-w-[1000px] px-5 pt-6 sm:px-8 sm:pt-8',
            )}
        >
            <nav aria-label="Migas de pan">
                <ol className="flex flex-wrap items-center gap-x-1.5 text-meta text-muted-foreground">
                    <li className="flex items-center gap-1.5">
                        <a href="/" className={CRUMB}>
                            Inicio
                        </a>
                        <ChevronRight aria-hidden className="size-3.5" />
                    </li>
                    <li className="flex items-center gap-1.5">
                        <a href="/blog" className={CRUMB}>
                            Blog
                        </a>
                    </li>
                </ol>
            </nav>
            <p className="mt-2">
                <a
                    href={article.toolUrl}
                    className="inline-flex min-h-11 items-center rounded-full bg-family-soft px-3.5 text-caption font-medium text-family hover:opacity-90"
                >
                    {article.toolName}
                </a>
            </p>
            <h1 className="mt-3 max-w-[820px] text-[34px] leading-[1.08] font-semibold tracking-[-0.03em] text-balance sm:text-5xl">
                {article.title}
            </h1>
            <p className="mt-5 max-w-[820px] text-lg leading-7 text-pretty text-muted-foreground sm:text-xl sm:leading-8">
                {article.description}
            </p>
            <p className="mt-5 flex flex-wrap gap-x-2 gap-y-1 text-caption text-muted-foreground">
                <span>{article.readingMinutes} min de lectura</span>
                {article.reviewedOn !== null && (
                    <>
                        <span aria-hidden>·</span>
                        <span>Revisado el {article.reviewedOn}</span>
                    </>
                )}
                {article.updatedOn !== null && (
                    <>
                        <span aria-hidden>·</span>
                        <span>Actualizado el {article.updatedOn}</span>
                    </>
                )}
            </p>
            <img
                src={article.cover}
                width={1200}
                height={630}
                alt={article.coverAlt}
                fetchPriority="high"
                className="mt-8 aspect-[1200/630] w-full rounded-2xl border object-cover"
            />
        </header>
    );
}
