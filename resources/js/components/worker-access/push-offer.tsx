import { Bell } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { usePushOffer } from '@/hooks/use-push-offer';

/**
 * «¿Le avisamos?»: asks the person, in their start and only after a first mark that was accepted, to allow the notices, and says
 * before the system's own box what they will say. The notice is always the same text; the detail is in Mi semana. «Ahora no»
 * hides it for 14 days. It says nothing where the browser cannot push, once allowed or refused, and on an iPhone without the app.
 */
export default function PushOffer({ slug }: { slug: string }) {
    const headingId = useId();
    const offer = usePushOffer(slug);

    if (!offer.visible) {
        return null;
    }

    return (
        <section
            aria-labelledby={headingId}
            className="flex flex-col gap-3 card p-4"
        >
            <div className="flex items-center gap-3.5">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                >
                    <Bell className="size-5" />
                </span>
                <h2 id={headingId} className="text-base font-semibold">
                    ¿Le avisamos?
                </h2>
            </div>
            <p className="text-base">
                ¿Le avisamos cuando Recursos Humanos revise sus marcas sin
                internet o responda sus correcciones? El aviso solo dirá «Tiene
                un aviso en Humetría». En seguida su teléfono le preguntará si
                lo permite.
            </p>
            <div className="flex flex-col gap-2">
                <Button
                    size="lg"
                    className="w-full text-base"
                    disabled={offer.working}
                    onClick={offer.accept}
                >
                    Sí, avisarme
                </Button>
                <Button
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    disabled={offer.working}
                    onClick={offer.later}
                >
                    Ahora no
                </Button>
            </div>
        </section>
    );
}
