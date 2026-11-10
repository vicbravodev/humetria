import DemoNextSteps from '@/components/landing/demo-next-steps';
import DemoRequestForm from '@/components/landing/demo-request-form';

export default function DemoSection() {
    return (
        <section
            id="agenda-una-demo"
            aria-labelledby="agenda-una-demo-title"
            className="scroll-mt-16 border-t bg-sidebar"
        >
            <div className="mx-auto flex max-w-[1240px] flex-wrap items-start gap-14 px-4 py-20 sm:px-6">
                <div className="flex flex-[1_1_320px] flex-col gap-10 lg:sticky lg:top-24">
                    <div className="flex flex-col gap-3.5">
                        <h2
                            id="agenda-una-demo-title"
                            className="text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] sm:text-[44px]"
                        >
                            Agenda una demo
                        </h2>
                        <p className="text-lg leading-7 text-muted-foreground">
                            Cuéntanos qué herramientas te interesan y te
                            mostramos Humetría con tu caso. La prueba gratis
                            también se agenda: la platicamos en la misma llamada
                            para que empieces con tu gente sin perder tiempo.
                        </p>
                    </div>
                    <DemoNextSteps />
                </div>
                <div className="min-w-0 flex-[1_1_460px]">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    );
}
