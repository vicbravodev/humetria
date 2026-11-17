import DemoSection from '@/components/landing/demo-section';
import LandingFooter from '@/components/landing/landing-footer';
import PrivacyNoticeHeader from '@/components/landing/privacy-notice-header';
import SeoHead from '@/components/landing/seo-head';
import ToolPageAudience from '@/components/landing/tool-page-audience';
import ToolPageFaqSection from '@/components/landing/tool-page-faq';
import ToolPageFeatures from '@/components/landing/tool-page-features';
import ToolPageHero from '@/components/landing/tool-page-hero';
import { toolByKey } from '@/components/landing/tool-page-links';
import ToolPageNormSection from '@/components/landing/tool-page-norm';
import ToolPageOthers from '@/components/landing/tool-page-others';
import { useLandingMeasurement } from '@/hooks/use-landing-measurement';
import { home } from '@/routes';
import type { Seo, ToolPageContent } from '@/types';

/** A tool's own page: what it solves, what the law asks, how Humetría does it, for whom, the questions and the demo. */
export default function Tool({
    seo,
    tool,
}: {
    seo: Seo;
    tool: ToolPageContent;
}) {
    useLandingMeasurement();

    return (
        <>
            <SeoHead seo={seo} />
            <PrivacyNoticeHeader demo />
            <main>
                <ToolPageHero page={tool} tool={toolByKey(tool.key)} />
                {tool.norm === null ? null : (
                    <ToolPageNormSection norm={tool.norm} />
                )}
                <ToolPageFeatures features={tool.features} />
                <ToolPageAudience page={tool} />
                <ToolPageFaqSection faqs={tool.faqs} />
                <DemoSection page={`tools.${tool.key}`} tool={tool.key} />
                <ToolPageOthers current={tool.key} />
            </main>
            <LandingFooter sectionBase={home().url} />
        </>
    );
}
