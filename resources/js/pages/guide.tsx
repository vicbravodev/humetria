import DemoSection from '@/components/landing/demo-section';
import GuideArticle from '@/components/landing/guide-article';
import GuideCta from '@/components/landing/guide-cta';
import GuideHero from '@/components/landing/guide-hero';
import LandingFooter from '@/components/landing/landing-footer';
import PrivacyNoticeHeader from '@/components/landing/privacy-notice-header';
import SeoHead from '@/components/landing/seo-head';
import { useLandingMeasurement } from '@/hooks/use-landing-measurement';
import { home } from '@/routes';
import type { GuideContent, Seo } from '@/types';

/** The guide to the workday reform of 2027: what the law says, article by article, and the way to the tool. */
export default function Guide({
    seo,
    guide,
}: {
    seo: Seo;
    guide: GuideContent;
}) {
    useLandingMeasurement();

    return (
        <>
            <SeoHead seo={seo} />
            <PrivacyNoticeHeader demo />
            <main>
                <GuideHero guide={guide} />
                <GuideArticle guide={guide} />
                <GuideCta />
                <DemoSection page="guides.workday-2027" tool="attendance" />
            </main>
            <LandingFooter sectionBase={home().url} />
        </>
    );
}
