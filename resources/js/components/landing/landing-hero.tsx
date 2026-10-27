import { Button } from '@/components/ui/button';
import HeroPreview from '@/components/landing/hero-preview';

export default function LandingHero() {
    return (
        <section
            aria-labelledby="hero-title"
            className="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-14 pb-16 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:px-8 lg:pt-24 lg:pb-12"
        >
            <div>
                <p className="text-[13px] leading-[18px] font-medium tracking-[0.02em] text-primary uppercase">
                    NOM-035-STPS-2018
                </p>
                <h1
                    id="hero-title"
                    className="mt-4 text-4xl leading-[44px] font-semibold tracking-[-0.01em] text-balance lg:text-[56px] lg:leading-[64px]"
                >
                    Aplica la <span className="whitespace-nowrap">NOM-035</span>{' '}
                    sin complicarte
                </h1>
                <p className="mt-6 max-w-xl text-lg leading-7 text-pretty text-muted-foreground">
                    Humetría invita a tu gente, recibe sus respuestas de forma
                    confidencial y te entrega el diagnóstico con apego a las
                    guías oficiales, listo para presentar ante la STPS.
                </p>
                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button asChild size="lg">
                        <a href="#agenda-una-demo" data-test="hero-demo">
                            Agenda una demo
                        </a>
                    </Button>
                    <Button asChild size="lg" variant="ghost">
                        <a href="#como-funciona">Ver cómo funciona</a>
                    </Button>
                </div>
            </div>
            <HeroPreview />
        </section>
    );
}
