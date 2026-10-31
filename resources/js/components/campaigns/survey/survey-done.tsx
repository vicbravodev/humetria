import ArcMeter from '@/components/humetria/arc-meter';

/** Every guide sent: the full arch, thanks, and that the link no longer opens anything. */
export default function SurveyDone() {
    return (
        <section className="flex w-full max-w-2xl flex-col items-center gap-6 pt-10 text-center">
            <ArcMeter value={1} max={1} label="100 %" size="md" />
            <div className="flex flex-col gap-2.5">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Gracias, terminó
                </h1>
                <p>
                    Ya contestó todo lo que le correspondía y sus respuestas se
                    enviaron.
                </p>
            </div>
            <p className="text-base text-muted-foreground">
                Ya puede cerrar esta página. El enlace dejó de funcionar para
                que nadie más lo use.
            </p>
        </section>
    );
}
