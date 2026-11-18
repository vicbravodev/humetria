import { WRAP } from '@/components/landing/landing-layout';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { attendance } from '@/routes/tools';

/** From the law to the tool: the page of the registro de jornada. */
export default function GuideCta() {
    return (
        <div className={cn(WRAP, 'max-w-[820px] pb-16 lg:pb-20')}>
            <div className="grid gap-5 rounded-2xl border bg-accent p-7 sm:p-10">
                <h2 className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">
                    Llega a enero con el registro funcionando
                </h2>
                <p className="max-w-[56ch] leading-7 text-pretty text-muted-foreground">
                    Humetría registra la jornada desde el celular de cada
                    persona, guarda el acuerdo y la confirmación de cada semana,
                    y arma el reporte para la autoridad.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button asChild size="lg" className="rounded-full">
                        <a
                            href={attendance().url}
                            data-track="cta_click"
                            data-track-target="guide_tool"
                        >
                            Ver el registro de jornada
                        </a>
                    </Button>
                    <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="rounded-full"
                    >
                        <a
                            href="#agenda-una-demo"
                            data-track="cta_click"
                            data-track-target="guide_demo"
                        >
                            Agenda una demo
                        </a>
                    </Button>
                </div>
            </div>
        </div>
    );
}
