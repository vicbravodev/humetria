import Logo from '@/components/humetria/logo';

/** Who sees what, said plainly: never more anonymity than each tool gives. */
export default function ComplianceSection() {
    return (
        <section
            aria-labelledby="confidencialidad"
            className="mx-auto max-w-[1200px] px-4 pb-16 sm:px-6 lg:pb-24"
        >
            <div
                data-test="compliance-privacy"
                className="flex flex-wrap items-center gap-8 rounded-[20px] bg-accent p-8 text-accent-foreground sm:gap-12 lg:p-14"
            >
                <span aria-hidden className="shrink-0">
                    <Logo kind="symbol" className="h-20 sm:h-[120px]" />
                </span>
                <div className="flex flex-[1_1_420px] flex-col gap-3.5">
                    <h2
                        id="confidencialidad"
                        className="scroll-mt-24 text-[26px] leading-[34px] font-semibold tracking-[-0.015em] sm:text-[32px] sm:leading-10"
                    >
                        La gente contesta con honestidad cuando sabe quién puede
                        ver lo que contesta
                    </h2>
                    <p className="text-lg leading-7">
                        En las encuestas confidenciales, RH nunca ve respuestas
                        individuales: los resultados por grupo aparecen solo con
                        5 o más respuestas. La Guía I de la NOM-035 y las listas
                        de verificación de la NOM-037 sí llevan nombre, porque
                        la norma lo pide, y solo las ven los roles autorizados.
                    </p>
                </div>
            </div>
        </section>
    );
}
