import BlogCard from '@/components/blog/blog-card';
import BlogPagination from '@/components/blog/blog-pagination';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import { LEAD, TITLE, WRAP } from '@/components/landing/landing-layout';
import MotionFrame from '@/components/landing/motion-frame';
import SeoHead from '@/components/landing/seo-head';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { BlogIndexProps } from '@/types/blog';

/** The blog: the newest article large, the rest in a grid, each with its cover. Rendered in Node, so a crawler reads it all. */
export default function BlogIndex({
    seo,
    articles,
    pagination,
}: BlogIndexProps) {
    const [first, ...rest] = articles;

    return (
        <MotionFrame>
            <SeoHead seo={seo} />
            <LandingHeader sectionBase={home().url} />
            <main className={cn(WRAP, 'pt-10 pb-16 sm:pt-12 lg:pt-16')}>
                <h1 className={TITLE}>Blog de cumplimiento laboral</h1>
                <p className={cn(LEAD, 'mt-4')}>
                    Lo que dice la norma, qué significa para tu empresa y cómo
                    dejarlo documentado. Cada artículo cita su fuente oficial y
                    lleva la fecha de su revisión.
                </p>
                {first === undefined ? (
                    <p className="mt-10 rounded-xl border bg-muted p-6 text-muted-foreground">
                        Todavía no hay artículos. Vuelve pronto.
                    </p>
                ) : (
                    <>
                        <div className="mt-10">
                            <BlogCard article={first} featured eager />
                        </div>
                        {rest.length > 0 && (
                            <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {rest.map((article) => (
                                    <li key={article.slug}>
                                        <BlogCard article={article} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
                <BlogPagination
                    previous={pagination.previous}
                    next={pagination.next}
                />
            </main>
            <LandingFooter sectionBase={home().url} />
        </MotionFrame>
    );
}
