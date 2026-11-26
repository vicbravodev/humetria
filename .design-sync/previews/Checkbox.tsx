import { Checkbox, Label } from 'humetria';

export const Checked = () => (
    <div className="flex items-center gap-3">
        <Checkbox id="notify" defaultChecked />
        <Label htmlFor="notify">Avisar por WhatsApp cuando se lance la evaluación</Label>
    </div>
);

export const List = () => (
    <div className="flex flex-col gap-3">
        <div className="flex items-center gap-3">
            <Checkbox id="g1" defaultChecked />
            <Label htmlFor="g1">Guía I · acontecimientos traumáticos severos</Label>
        </div>
        <div className="flex items-center gap-3">
            <Checkbox id="g3" defaultChecked />
            <Label htmlFor="g3">Guía III · factores de riesgo psicosocial</Label>
        </div>
        <div className="flex items-center gap-3">
            <Checkbox id="g2" />
            <Label htmlFor="g2">Guía II · centros de 16 a 50 personas</Label>
        </div>
    </div>
);

export const Disabled = () => (
    <div className="flex flex-col gap-3">
        <div className="flex items-center gap-3">
            <Checkbox id="d1" defaultChecked disabled />
            <Label htmlFor="d1">Guía I (obligatoria en todo centro)</Label>
        </div>
        <div className="flex items-center gap-3">
            <Checkbox id="d2" disabled />
            <Label htmlFor="d2">Guía II (no aplica a más de 50 personas)</Label>
        </div>
    </div>
);

export const Invalid = () => (
    <div className="flex flex-col gap-2">
        <div className="flex items-center gap-3">
            <Checkbox id="terms" aria-invalid />
            <Label htmlFor="terms">Leí y acepto la política de teletrabajo</Label>
        </div>
        <p className="text-sm text-destructive">Acepta la política para continuar.</p>
    </div>
);
