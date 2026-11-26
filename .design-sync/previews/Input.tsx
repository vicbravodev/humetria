import { Input, Label } from 'humetria';

export const WithLabel = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="employee-name">Nombre completo</Label>
        <Input id="employee-name" defaultValue="Mariana López Treviño" />
    </div>
);

export const Placeholder = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="employee-email">Correo</Label>
        <Input id="employee-email" type="email" placeholder="nombre@empresa.com" />
    </div>
);

export const Error = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="employee-curp">CURP</Label>
        <Input id="employee-curp" defaultValue="LOTM900312" aria-invalid />
        <p className="text-sm text-destructive">La CURP debe tener 18 caracteres.</p>
    </div>
);

export const Disabled = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="center-name">Centro de trabajo</Label>
        <Input id="center-name" defaultValue="Planta Apodaca" disabled />
    </div>
);
