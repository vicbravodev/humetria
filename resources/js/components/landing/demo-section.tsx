import DemoRequestForm from '@/components/landing/demo-request-form';

export default function DemoSection() {
    return (
        <section
            id="agenda-una-demo"
            aria-labelledby="agenda-una-demo-title"
            className="scroll-mt-16 border-t bg-sidebar"
        >
            <div className="mx-auto flex max-w-[1200px] flex-wrap items-start gap-14 px-4 py-16 sm:px-6 lg:py-[88px]">
                <div className="flex flex-[1_1_320px] flex-col gap-3.5">
                    <h2
                        id="agenda-una-demo-title"
                        className="text-[28px] leading-9 font-semibold tracking-[-0.015em] sm:text-4xl sm:leading-[44px]"
                    >
                        Agenda una demo
                    </h2>
                    <p className="text-lg leading-7 text-muted-foreground">
                        Cuéntanos qué herramientas te interesan y te mostramos
                        Humetría con tu caso. La prueba gratis también se
                        agenda: la platicamos en la misma llamada para que
                        empieces con tu gente sin perder tiempo.
                    </p>
                </div>
                <div className="min-w-0 flex-[1_1_460px]">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    );
}
