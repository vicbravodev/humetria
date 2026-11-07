import { Fingerprint } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * The person accepts the agreement and has their phone create the key. The button waits for the checkbox; the error,
 * if any, is said plainly.
 */
export default function EnrollForm({
    working,
    error,
    onEnroll,
}: {
    working: boolean;
    error: string | null;
    onEnroll: () => void;
}) {
    const [accepted, setAccepted] = useState(false);

    return (
        <section
            aria-label="Aceptar y vincular"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex items-start gap-3">
                <Checkbox
                    id="agreement-accepted"
                    className="mt-1"
                    checked={accepted}
                    onCheckedChange={(checked) => setAccepted(checked === true)}
                />
                <Label htmlFor="agreement-accepted" className="text-base">
                    Leí el acuerdo y lo acepto.
                </Label>
            </div>
            <p className="text-base text-muted-foreground">
                Su teléfono le pedirá su huella, su rostro o su PIN para crear
                una llave de acceso. La llave es solo suya y se abre con su
                huella, su rostro o su PIN cada vez que marque.
            </p>
            {error !== null && (
                <p role="alert" className="text-base text-destructive">
                    {error}
                </p>
            )}
            <Button
                size="lg"
                disabled={!accepted || working}
                onClick={onEnroll}
            >
                {working ? <Spinner /> : <Fingerprint aria-hidden />}
                Aceptar y vincular este teléfono
            </Button>
        </section>
    );
}
