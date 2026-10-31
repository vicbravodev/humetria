import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** The consent for health-related answers and photos (criterio C-25): accept or decline, both let them send the list. */
export default function ChecklistConsent({
    text,
    consent,
    onDecide,
}: {
    text: string;
    consent: boolean | null;
    onDecide: (consent: boolean) => void;
}) {
    return (
        <section
            aria-labelledby="consent-title"
            className="grid gap-3 rounded-xl border bg-card p-4"
        >
            <h2
                id="consent-title"
                className="flex items-center gap-2 text-xl font-semibold"
            >
                <ShieldCheck aria-hidden className="size-5 text-primary" />
                Consentimiento
            </h2>
            <p className="text-base whitespace-pre-line text-muted-foreground">
                {text}
            </p>
            <div
                role="radiogroup"
                aria-label="Consentimiento"
                className="flex flex-col gap-2.5 sm:flex-row"
            >
                <Button
                    type="button"
                    role="radio"
                    aria-checked={consent === true}
                    variant={consent === true ? 'default' : 'outline'}
                    className="h-12 text-base sm:flex-1"
                    onClick={() => onDecide(true)}
                >
                    Acepto
                </Button>
                <Button
                    type="button"
                    role="radio"
                    aria-checked={consent === false}
                    variant={consent === false ? 'default' : 'outline'}
                    className="h-12 text-base sm:flex-1"
                    onClick={() => onDecide(false)}
                >
                    No acepto
                </Button>
            </div>
            {consent === false && (
                <p className="text-base text-muted-foreground">
                    Podrá enviar su lista igual: las preguntas de salud y las
                    fotos quedarán sin contestar.
                </p>
            )}
        </section>
    );
}
