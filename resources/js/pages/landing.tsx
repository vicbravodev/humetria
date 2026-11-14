import { domMax, LazyMotion, MotionConfig } from 'motion/react';
import AudienceSection from '@/components/landing/audience-section';
import ComplianceSection from '@/components/landing/compliance-section';
import DemoSection from '@/components/landing/demo-section';
import HowItWorksSection from '@/components/landing/how-it-works-section';
import HumiSection from '@/components/landing/humi-section';
import InspectionSection from '@/components/landing/inspection-section';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import LandingHero from '@/components/landing/landing-hero';
import SeoHead from '@/components/landing/seo-head';
import ToolsSection from '@/components/landing/tools-section';
import TrustStrip from '@/components/landing/trust-strip';
import { cspNonce } from '@/lib/csp-nonce';
import type { Seo } from '@/types';

export default function Landing({ seo }: { seo: Seo }) {
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
                    <HowItWorksSection />
                    <ToolsSection />
                    <InspectionSection />
                    <ComplianceSection />
                    <HumiSection />
                    <AudienceSection />
                    <DemoSection />
                </main>
                <LandingFooter />
            </MotionConfig>
        </LazyMotion>
    );
}
