import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import AudienceSection from '@/components/landing/audience-section';
import ComplianceSection from '@/components/landing/compliance-section';
import DemoSection from '@/components/landing/demo-section';
import HowItWorksSection from '@/components/landing/how-it-works-section';
import HumiSection from '@/components/landing/humi-section';
import InspectionSection from '@/components/landing/inspection-section';
import LandingFaq from '@/components/landing/landing-faq';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import LandingHero from '@/components/landing/landing-hero';
import LegalCalendar from '@/components/landing/legal-calendar';
import PricingSection from '@/components/landing/pricing-section';
import SeoHead from '@/components/landing/seo-head';
import ToolsSection from '@/components/landing/tools-section';
import TrustStrip from '@/components/landing/trust-strip';
import { useLandingMeasurement } from '@/hooks/use-landing-measurement';
import { useScrollReveal } from '@/hooks/use-scroll-reveal';
import { cspNonce } from '@/lib/csp-nonce';
import type { Seo } from '@/types';

export default function Landing({ seo }: { seo: Seo }) {
    useScrollReveal();
    useLandingMeasurement();

    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                <SeoHead seo={seo} />
                <LandingHeader />
                <main data-smooth-scroll>
                    <LandingHero />
                    <TrustStrip />
                    <LegalCalendar />
                    <ToolsSection />
                    <HowItWorksSection />
                    <InspectionSection />
                    <ComplianceSection />
                    <HumiSection />
                    <AudienceSection />
                    <PricingSection />
                    <LandingFaq />
                    <DemoSection />
                </main>
                <LandingFooter />
            </MotionConfig>
        </LazyMotion>
    );
}
