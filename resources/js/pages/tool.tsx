import DemoSection from '@/components/landing/demo-section';
import LandingFooter from '@/components/landing/landing-footer';
import SeoHead from '@/components/landing/seo-head';
import StickyDemoBar from '@/components/landing/sticky-demo-bar';
import ToolPageAudience from '@/components/landing/tool-page-audience';
import ToolPageBeforeAfter from '@/components/landing/tool-page-before-after';
import ToolPageFaqSection from '@/components/landing/tool-page-faq';
import ToolPageFeatures from '@/components/landing/tool-page-features';
import ToolPageHeader from '@/components/landing/tool-page-header';
import ToolPageHero from '@/components/landing/tool-page-hero';
import { toolByKey } from '@/components/landing/tool-page-links';
import ToolPageNormSection from '@/components/landing/tool-page-norm';
import ToolPageOthers from '@/components/landing/tool-page-others';
import ToolPagePickSection from '@/components/landing/tool-page-pick';
import ToolPagePrivacy from '@/components/landing/tool-page-privacy';
import ToolPageSteps from '@/components/landing/tool-page-steps';
import { useLandingMeasurement } from '@/hooks/use-landing-measurement';
import { home } from '@/routes';
import type { Seo, ToolPageContent } from '@/types';

/** A tool's own page: what it solves, what the law asks, how it works with real screens, for whom, the questions and the demo. */
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
            <ToolPageHeader />
            <main>
                <ToolPageHero page={tool} tool={toolByKey(tool.key)} />
                {tool.norm === null ? null : (
                    <ToolPageNormSection
                        norm={tool.norm}
                        guideLink={tool.guideLink}
                    />
                )}
                {tool.pick === null ? null : (
                    <ToolPagePickSection pick={tool.pick} />
                )}
                <ToolPageSteps steps={tool.steps} />
                <ToolPageFeatures features={tool.features} />
                <ToolPageBeforeAfter page={tool} />
                <ToolPageAudience page={tool} />
                {tool.privacy === null ? null : (
                    <ToolPagePrivacy privacy={tool.privacy} />
                )}
                <ToolPageFaqSection faqs={tool.faqs} />
                <DemoSection page={`tools.${tool.key}`} tool={tool.key} />
                <ToolPageOthers related={tool.related} />
            </main>
            <LandingFooter sectionBase={home().url} />
            <StickyDemoBar />
        </>
    );
}
