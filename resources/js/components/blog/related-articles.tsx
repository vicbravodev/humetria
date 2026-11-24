import BlogCard from '@/components/blog/blog-card';
import type { BlogCardData } from '@/types/blog';

/** Up to three more articles, those of the same tool first. */
export default function RelatedArticles({
    articles,
}: {
    articles: BlogCardData[];
}) {
    if (articles.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="related"
            className="mx-auto mt-16 w-full max-w-[1200px] px-5 sm:px-8 lg:px-10"
        >
            <h2
                id="related"
                className="text-2xl font-semibold tracking-[-0.02em]"
            >
                Sigue leyendo
            </h2>
            <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {articles.map((article) => (
                    <li key={article.slug}>
                        <BlogCard article={article} heading="h3" />
                    </li>
                ))}
            </ul>
        </section>
    );
}
