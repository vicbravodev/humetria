import { Head } from '@inertiajs/react';
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
import ToolsSection from '@/components/landing/tools-section';
import { cspNonce } from '@/lib/csp-nonce';

export default function Landing() {
    return (
        <LazyMotion features={domMax} strict>
            <MotionConfig
                reducedMotion="user"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
            >
                <Head title="Herramientas de RH para cumplir con la STPS">
                    <meta
                        name="description"
                        content="Humetría reúne NOM-035, NOM-037, Ley Silla, buzón de quejas y Compensación: invita a tu gente, recolecta con la confidencialidad que requiere cada herramienta y te entrega el diagnóstico, el plan y la evidencia para la STPS."
                    />
                </Head>
                <LandingHeader />
                <main data-smooth-scroll>
                    <LandingHero />
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
