import { FAMILY_CLASS } from '@/lib/blog-family';
import { cn } from '@/lib/utils';
import type { BlogCardData } from '@/types/blog';

/** A card of the blog: cover, its tool in its family's color, title and description. The whole card is the link (the title's, stretched). */
export default function BlogCard({
    article,
    featured = false,
    eager = false,
    heading: Heading = 'h2',
}: {
    article: BlogCardData;
    featured?: boolean;
    /** The first thing painted: loaded with priority instead of lazily. */
    eager?: boolean;
    heading?: 'h2' | 'h3';
}) {
    return (
        <article
            className={cn(
                FAMILY_CLASS[article.family],
                'group relative flex h-full flex-col overflow-hidden rounded-2xl border bg-card text-card-foreground transition-shadow duration-200 hover:shadow-raised',
                featured && 'lg:grid lg:grid-cols-[1.15fr_1fr]',
            )}
        >
            <img
                src={article.cover}
                width={1200}
                height={630}
                alt={`Portada: ${article.toolName}`}
                {...(eager
                    ? { fetchPriority: 'high' as const }
                    : { loading: 'lazy' as const, decoding: 'async' as const })}
                className={cn(
                    'aspect-[1200/630] w-full object-cover',
                    featured && 'lg:h-full lg:min-h-[320px]',
                )}
            />
            <div
                className={cn(
                    'flex flex-1 flex-col gap-3 p-5 sm:p-6',
                    featured && 'lg:justify-center lg:p-10',
                )}
            >
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-muted-foreground">
                    <span className="rounded-full bg-family-soft px-2.5 py-0.5 font-medium text-family">
                        {article.toolName}
                    </span>
                    <span>
                        <time dateTime={article.publishedAt}>
                            {article.publishedOn}
                        </time>{' '}
                        · {article.readingMinutes} min de lectura
                    </span>
                </p>
                <Heading
                    className={cn(
                        'font-semibold tracking-[-0.02em] text-balance',
                        featured
                            ? 'text-2xl leading-tight sm:text-[32px] sm:leading-[1.1]'
                            : 'text-xl leading-snug',
                    )}
                >
                    <a
                        href={article.url}
                        className="inline-flex min-h-11 items-center rounded-md group-hover:text-family after:absolute after:inset-0 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {article.title}
                    </a>
                </Heading>
                <p
                    className={cn(
                        'text-meta text-muted-foreground',
                        featured && 'sm:text-base',
                    )}
                >
                    {article.description}
                </p>
            </div>
        </article>
    );
}
