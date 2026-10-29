import { Head } from '@inertiajs/react';
import AudienceSection from '@/components/landing/audience-section';
import ComplianceSection from '@/components/landing/compliance-section';
import DemoSection from '@/components/landing/demo-section';
import HowItWorksSection from '@/components/landing/how-it-works-section';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import LandingHero from '@/components/landing/landing-hero';
import ToolsSection from '@/components/landing/tools-section';

export default function Landing() {
    return (
        <>
            <Head title="Herramientas de RH para cumplir con la STPS">
                <meta
                    name="description"
                    content="Humetría reúne NOM-035, NOM-037 y Ley Silla: invita a tu gente, recolecta con la confidencialidad que requiere cada herramienta y te entrega el diagnóstico, el plan y la evidencia para la STPS."
                />
            </Head>
            <LandingHeader />
            <main>
                <LandingHero />
                <ToolsSection />
                <AudienceSection />
                <HowItWorksSection />
                <ComplianceSection />
                <DemoSection />
            </main>
            <LandingFooter />
        </>
    );
}
