import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    error?: string | undefined;
};

export default function DemoMessageField({ error }: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="demo-message">
                Mensaje{' '}
                <span className="font-normal text-muted-foreground">
                    (opcional)
                </span>
            </Label>
            <Textarea
                className="min-h-28"
                id="demo-message"
                name="message"
                rows={4}
                maxLength={2000}
                placeholder="Cuéntanos qué necesitas o cuándo te conviene la llamada"
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={
                    error === undefined ? undefined : 'demo-message-error'
                }
            />
            <InputError id="demo-message-error" role="alert" message={error} />
        </div>
    );
}
