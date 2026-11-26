import { Checkbox, Input, Label } from 'humetria';

export const WithInput = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="center-name">Nombre del centro</Label>
        <Input id="center-name" defaultValue="Planta Apodaca" />
    </div>
);

export const WithCheckbox = () => (
    <div className="flex items-center gap-3">
        <Checkbox id="notify-owner" defaultChecked />
        <Label htmlFor="notify-owner">Avisarme cuando cierre la evaluación</Label>
    </div>
);

export const DisabledPeer = () => (
    <div className="flex items-center gap-3">
        <Checkbox id="send-whatsapp" className="peer" disabled />
        <Label htmlFor="send-whatsapp">Enviar también por WhatsApp</Label>
    </div>
);
