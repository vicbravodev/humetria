import { Input, InputError, Label } from 'humetria';

export const UnderAField = () => (
    <div className="grid gap-2 max-w-xs">
        <Label htmlFor="email">Correo</Label>
        <Input id="email" name="email" defaultValue="ana.lopez@" aria-invalid aria-describedby="email-error" />
        <InputError id="email-error" message="Escribe un correo válido, como ana.lopez@empresa.mx." />
    </div>
);

export const Alone = () => <InputError message="Elige al menos un centro de trabajo." />;
