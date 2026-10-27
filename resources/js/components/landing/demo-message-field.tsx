import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

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
            <textarea
                id="demo-message"
                name="message"
                rows={4}
                maxLength={2000}
                placeholder="Cuéntanos qué necesitas o cuándo te conviene la llamada"
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={
                    error === undefined ? undefined : 'demo-message-error'
                }
                className="min-h-28 w-full rounded-md border border-input bg-card px-4 py-3 text-base transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive"
            />
            <InputError id="demo-message-error" message={error} />
        </div>
    );
}
