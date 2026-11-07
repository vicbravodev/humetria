import PrivacyDemo from '@/components/landing/privacy-demo';
import Reveal from '@/components/landing/reveal';

/** Who sees what, said plainly and shown: never more anonymity than each tool gives. */
export default function ComplianceSection() {
    return (
        <section aria-labelledby="confidencialidad">
            <div className="mx-auto grid max-w-[1240px] items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-20 lg:py-28">
                <Reveal className="flex flex-col gap-5">
                    <div
                        data-test="compliance-privacy"
                        className="flex flex-col gap-5"
                    >
                        <h2
                            id="confidencialidad"
                            className="scroll-mt-24 text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] text-balance sm:text-[44px]"
                        >
                            La gente contesta con honestidad cuando sabe quién
                            puede ver lo que contesta
                        </h2>
                        <p className="max-w-[52ch] text-lg leading-7 text-muted-foreground">
                            En las encuestas confidenciales, RH nunca ve
                            respuestas individuales: los resultados por grupo
                            aparecen solo con 5 o más respuestas. La Guía I de
                            la NOM-035 y las listas de verificación de la
                            NOM-037 sí llevan nombre, porque la norma lo pide, y
                            solo las ven los roles autorizados.
                        </p>
                    </div>
                </Reveal>
                <Reveal delay={0.1}>
                    <PrivacyDemo />
                </Reveal>
            </div>
        </section>
    );
}
