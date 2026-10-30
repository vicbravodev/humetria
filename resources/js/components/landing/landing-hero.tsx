import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import HeroPreview from '@/components/landing/hero-preview';

export default function LandingHero() {
    return (
        <section
            aria-labelledby="hero-title"
            className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-14 px-4 pt-14 pb-16 sm:px-6 lg:pt-[88px] lg:pb-24"
        >
            <div className="flex flex-[1_1_480px] flex-col gap-6">
                <h1
                    id="hero-title"
                    className="max-w-[620px] text-4xl leading-[44px] font-semibold tracking-[-0.025em] lg:text-[56px] lg:leading-[62px]"
                >
                    Cumple con la STPS y cuida a tu gente, sin complicarte
                </h1>
                <p className="max-w-[540px] text-lg leading-7 text-pretty text-muted-foreground lg:text-[19px] lg:leading-[30px]">
                    Humetría invita a tu gente, recolecta sus respuestas con la
                    confidencialidad que requiere cada herramienta y te entrega
                    lo que pide la autoridad: el diagnóstico, el plan y la
                    evidencia.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button asChild size="lg">
                        <a href="#agenda-una-demo" data-test="hero-demo">
                            Agenda una demo
                        </a>
                    </Button>
                    <Button asChild size="lg" variant="ghost">
                        <a href="#como-funciona">Ver cómo funciona</a>
                    </Button>
                </div>
                <p
                    data-test="hero-tools"
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ShieldCheck aria-hidden className="size-4 shrink-0" />
                    <span>
                        NOM-035-STPS-2018 · NOM-037-STPS-2023 · Ley Silla
                    </span>
                </p>
            </div>
            <div className="flex flex-[1_1_380px] justify-center">
                <HeroPreview />
            </div>
        </section>
    );
}
