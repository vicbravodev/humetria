import ArticleBody from '@/components/blog/article-body';
import ArticleColumns from '@/components/blog/article-columns';
import ArticleCta from '@/components/blog/article-cta';
import ArticleHeader from '@/components/blog/article-header';
import ArticleToc from '@/components/blog/article-toc';
import RelatedArticles from '@/components/blog/related-articles';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import MotionFrame from '@/components/landing/motion-frame';
import SeoHead from '@/components/landing/seo-head';
import { FAMILY_CLASS } from '@/lib/blog-family';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { BlogShowProps } from '@/types/blog';

/** One article: header with cover, a table of contents, the body and its sources, the demo and more to read. Rendered in Node. */
export default function BlogShow({ seo, article, related }: BlogShowProps) {
    return (
        <MotionFrame>
            <SeoHead seo={seo} />
            <LandingHeader sectionBase={home().url} />
            <main className={cn(FAMILY_CLASS[article.family], 'pb-16')}>
                <ArticleHeader article={article} />
                <ArticleColumns>
                    <ArticleToc headings={article.headings} />
                    <ArticleBody article={article} />
                </ArticleColumns>
                <ArticleCta
                    toolName={article.toolName}
                    toolUrl={article.toolUrl}
                />
                <RelatedArticles articles={related} />
            </main>
            <LandingFooter sectionBase={home().url} />
        </MotionFrame>
    );
}
