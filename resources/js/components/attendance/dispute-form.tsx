import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** Why the person does not agree with their week, in their words; sending it asks for their fingerprint, face or PIN. */
export default function DisputeForm({
    working,
    onSend,
    onCancel,
}: {
    working: boolean;
    onSend: (reason: string) => void;
    onCancel: () => void;
}) {
    const [reason, setReason] = useState('');

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                onSend(reason);
            }}
        >
            <Label htmlFor="dispute-reason" className="text-base">
                ¿Qué no está bien en su semana?
            </Label>
            <Textarea
                id="dispute-reason"
                name="reason"
                required
                minLength={10}
                maxLength={1000}
                rows={4}
                className="text-base"
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                aria-describedby="dispute-reason-hint"
            />
            <p
                id="dispute-reason-hint"
                className="text-base text-muted-foreground"
            >
                Por ejemplo: «El jueves salí a las 20:00, no a las 18:00».
                Recursos Humanos lo revisará.
            </p>
            <div className="flex flex-col gap-2">
                <Button
                    type="submit"
                    size="lg"
                    className="w-full text-base"
                    disabled={working}
                >
                    Enviar mi objeción
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    className="w-full text-base"
                    disabled={working}
                    onClick={onCancel}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}
