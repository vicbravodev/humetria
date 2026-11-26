import { Label, Textarea } from 'humetria';

export const WithLabel = () => (
    <div className="flex max-w-md flex-col gap-2">
        <Label htmlFor="reason">Motivo del rechazo</Label>
        <Textarea
            id="reason"
            defaultValue="Ese periodo coincide con el cierre de inventario de Planta Apodaca. ¿Puedes moverlo a la segunda semana de enero?"
        />
    </div>
);

export const Placeholder = () => (
    <div className="flex max-w-md flex-col gap-2">
        <Label htmlFor="action-notes">Descripción de la acción</Label>
        <Textarea id="action-notes" placeholder="Qué se hará, quién la encabeza y cómo se medirá" />
    </div>
);

export const Error = () => (
    <div className="flex max-w-md flex-col gap-2">
        <Label htmlFor="complaint">Relato de los hechos</Label>
        <Textarea id="complaint" aria-invalid />
        <p className="text-sm text-destructive">Cuéntanos qué pasó para poder darle seguimiento.</p>
    </div>
);

export const Disabled = () => (
    <div className="flex max-w-md flex-col gap-2">
        <Label htmlFor="closed-notes">Notas del caso</Label>
        <Textarea id="closed-notes" defaultValue="Caso cerrado el 2 de noviembre." disabled />
    </div>
);
