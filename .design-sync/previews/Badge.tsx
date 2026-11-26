import { Badge } from 'humetria';

export const Variants = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge>En curso</Badge>
        <Badge variant="secondary">Borrador</Badge>
        <Badge variant="success">Al día</Badge>
        <Badge variant="warning">Sin contacto</Badge>
        <Badge variant="destructive">Vencida</Badge>
        <Badge variant="outline">Guía III</Badge>
    </div>
);

export const CampaignStates = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge variant="secondary">Borrador</Badge>
        <Badge>En curso</Badge>
        <Badge variant="secondary">Cerrada</Badge>
    </div>
);

export const LeaveRequests = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge variant="warning">Pendiente</Badge>
        <Badge variant="success">Aprobada</Badge>
        <Badge variant="destructive">Rechazada</Badge>
    </div>
);

export const Categories = () => (
    <div className="flex flex-wrap items-center gap-2">
        <Badge variant="outline">Teletrabajo</Badge>
        <Badge variant="outline">Planta Apodaca</Badge>
        <Badge variant="warning">Sensible</Badge>
    </div>
);
