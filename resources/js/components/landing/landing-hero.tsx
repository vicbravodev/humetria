import HeroFamilies from '@/components/landing/hero-families';
import HeroFilm from '@/components/landing/hero-film';
import HeroHeadline from '@/components/landing/hero-headline';
import { WRAP } from '@/components/landing/landing-layout';
import { Button } from '@/components/ui/button';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

/** Fades the parts under the headline in once it has landed (CSS, `enter-after`): the class that holds each one back. */
const after = (delay: number) => cn('enter-after', enterDelay(delay));

/** The promise and the film beside it; under both, the nine tools as three families. */
export default function LandingHero() {
    return (
        <section
            id="inicio"
            aria-labelledby="hero-title"
            className="relative overflow-clip"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-[-20%] top-[-10%] h-[80%] bg-[radial-gradient(60%_60%_at_70%_30%,color-mix(in_srgb,var(--primary)_14%,transparent),transparent_70%)]"
            />
            <div
                className={cn(
                    WRAP,
                    'relative grid gap-10 pt-8 pb-10 sm:pt-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)] lg:items-center lg:gap-x-16 lg:gap-y-14 lg:pt-14 lg:pb-16',
                )}
            >
                <div className="flex flex-col gap-6">
                    <HeroHeadline />
                    <p
                        className={cn(
                            after(0.55),
                            'max-w-[46ch] text-lg leading-7 text-pretty text-muted-foreground lg:text-xl lg:leading-8',
                        )}
                    >
                        Humetría invita a tu gente, recolecta sus respuestas con
                        la confidencialidad que requiere cada herramienta y te
                        deja listos los reportes que te pide la STPS.
                    </p>
                    <div
                        className={cn(
                            after(0.65),
                            'grid gap-3 min-[520px]:flex min-[520px]:flex-wrap',
                        )}
                    >
                        <Button
                            asChild
                            size="lg"
                            className="h-13 px-6 text-base"
                        >
                            <a
                                data-track="cta_click"
                                data-track-target="hero_demo"
                                href="#agenda-una-demo"
                                data-test="hero-demo"
                            >
                                Agenda una demo
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-13 px-6 text-base"
                        >
                            <a
                                data-track="cta_click"
                                data-track-target="hero_how_it_works"
                                href="#como-funciona"
                            >
                                Ver cómo funciona
                            </a>
                        </Button>
                    </div>
                </div>
                <div className={after(0.2)}>
                    <HeroFilm />
                </div>
                <div className={cn(after(0.75), 'lg:col-span-2')}>
                    <HeroFamilies />
                </div>
            </div>
        </section>
    );
}
