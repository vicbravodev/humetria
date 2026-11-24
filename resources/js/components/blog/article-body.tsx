import type { BlogArticleData } from '@/types/blog';

/** The body the server rendered from Markdown, and the official sources the article rests on. */
export default function ArticleBody({ article }: { article: BlogArticleData }) {
    return (
        <article className="max-w-[68ch] min-w-0 lg:col-start-2">
            <div
                className="blog-prose"
                dangerouslySetInnerHTML={{ __html: article.html }}
            />
            <section aria-labelledby="sources" className="mt-12 border-t pt-8">
                <h2
                    id="sources"
                    className="text-xl font-semibold tracking-[-0.02em]"
                >
                    Fuentes oficiales
                </h2>
                <ul className="mt-4 grid gap-1 text-meta">
                    {article.sources.map((source) => (
                        <li key={source}>
                            <a
                                href={source}
                                rel="noopener"
                                className="inline-flex min-h-11 items-center break-all text-primary underline underline-offset-4"
                            >
                                {source}
                            </a>
                        </li>
                    ))}
                </ul>
            </section>
        </article>
    );
}
