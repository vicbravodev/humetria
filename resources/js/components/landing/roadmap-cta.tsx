import { WRAP } from '@/components/landing/landing-layout';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

/** The close of the page: what is missing for you? The way to tell us is the demo form of the landing. */
export default function RoadmapCta() {
    return (
        <div className={cn(WRAP, 'pb-16 lg:pb-24')}>
            <div className="grid gap-5 rounded-2xl border bg-accent p-7 sm:p-10 md:grid-cols-[1fr_auto] md:items-center">
                <div className="grid gap-2">
                    <h2 className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">
                        ¿Te falta algo que tu equipo necesita?
                    </h2>
                    <p className="max-w-[56ch] leading-7 text-pretty text-muted-foreground">
                        Cuéntanos qué herramienta esperas. Lo que más nos piden
                        sube en este orden.
                    </p>
                </div>
                <Button asChild size="lg" className="rounded-full">
                    <a href={`${home().url}#agenda-una-demo`}>
                        Agenda una demo
                    </a>
                </Button>
            </div>
        </div>
    );
}
