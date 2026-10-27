import { Head } from '@inertiajs/react';
import AudienceSection from '@/components/landing/audience-section';
import ComplianceSection from '@/components/landing/compliance-section';
import DemoSection from '@/components/landing/demo-section';
import HowItWorksSection from '@/components/landing/how-it-works-section';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import LandingHero from '@/components/landing/landing-hero';

export default function Landing() {
    return (
        <>
            <Head title="Aplica la NOM-035 sin complicarte">
                <meta
                    name="description"
                    content="Humetría aplica las guías de la NOM-035 a tu gente de forma confidencial y te entrega el diagnóstico y el informe para la STPS."
                />
            </Head>
            <LandingHeader />
            <main>
                <LandingHero />
                <AudienceSection />
                <HowItWorksSection />
                <ComplianceSection />
                <DemoSection />
            </main>
            <LandingFooter />
        </>
    );
}
