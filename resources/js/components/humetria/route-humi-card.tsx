import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { openHumi } from '@/lib/assistant/open-humi';

/** «¿Una etapa no te queda clara?» (boards V2-Ruta-N037 and V2-Ruta-Silla): the way to Humi, for whoever may use it. */
export default function RouteHumiCard({
    example,
}: {
    /** What to ask, in the words of the standard: «qué es la reversibilidad o qué multa hay». */
    example: string;
}) {
    const { props } = usePage();

    if (
        props.tenant === null ||
        props.tenant === undefined ||
        !props.tenant.can.use_assistant
    ) {
        return null;
    }

    return (
        <section
            aria-labelledby="humi-help"
            className="flex flex-col gap-3 rounded-2xl bg-accent p-[22px]"
        >
            <h2 id="humi-help" className="text-sm font-semibold">
                ¿Una etapa no te queda clara?
            </h2>
            <p className="text-sm">
                Humi te explica qué pide la norma con tus datos, sin jerga.
                Pregúntale, por ejemplo, {example}.
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={openHumi}
            >
                Pregúntale a Humi
            </Button>
        </section>
    );
}
