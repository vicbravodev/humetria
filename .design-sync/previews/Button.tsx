import { Button } from 'humetria';
import { Download, Ellipsis, Send, Trash2, UserPlus } from 'lucide-react';

export const Variants = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button>Ver avance</Button>
        <Button variant="outline">Enviar recordatorio</Button>
        <Button variant="secondary">Guardar borrador</Button>
        <Button variant="ghost">Cancelar</Button>
        <Button variant="destructive">Cerrar evaluación</Button>
        <Button variant="link">Ver criterios de la NOM-035</Button>
    </div>
);

export const WithIcon = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button>
            <UserPlus />
            Agregar empleado
        </Button>
        <Button variant="outline">
            <Download />
            Descargar informe
        </Button>
        <Button variant="destructive">
            <Trash2 />
            Eliminar centro
        </Button>
    </div>
);

export const Sizes = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button size="sm" variant="outline">
            Reenviar invitación
        </Button>
        <Button>Lanzar evaluación</Button>
        <Button size="lg">
            <Send />
            Enviar invitaciones
        </Button>
        <Button size="icon" variant="ghost" aria-label="Acciones de Mariana López">
            <Ellipsis className="size-5" />
        </Button>
    </div>
);

export const Disabled = () => (
    <div className="flex flex-wrap items-center gap-3">
        <Button disabled>Lanzar evaluación</Button>
        <Button variant="outline" disabled>
            Enviar recordatorio
        </Button>
    </div>
);
