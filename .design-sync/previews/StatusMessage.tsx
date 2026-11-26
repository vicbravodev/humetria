import { Button, StatusMessage } from 'humetria';
import { Clock, Lock, SearchX } from 'lucide-react';

export const Forbidden = () => (
    <StatusMessage
        icon={Lock}
        caption="Error 403"
        title="Esta sección no está en tu rol"
        actions={
            <>
                <Button variant="outline">Volver</Button>
                <Button>Ir al panel</Button>
            </>
        }
    >
        Tu rol puede ver otras partes del panel. Si necesitas entrar aquí, pídele al dueño de la empresa que te dé el
        permiso.
    </StatusMessage>
);

export const NotFound = () => (
    <StatusMessage
        icon={SearchX}
        caption="Error 404"
        title="No encontramos esta página"
        actions={<Button>Ir al panel</Button>}
    >
        Quizá el enlace cambió o la evaluación ya no existe. Revisa la dirección o vuelve al inicio.
    </StatusMessage>
);

export const ExpiredLink = () => (
    <StatusMessage icon={Clock} caption="Enlace vencido" title="Este enlace ya se usó">
        Cada enlace de la encuesta sirve una sola vez. Si aún no contesta, pida a Recursos Humanos que le envíen uno
        nuevo.
    </StatusMessage>
);

export const WithoutCaption = () => (
    <StatusMessage icon={Lock} title="Acceso en pausa">
        Mientras se reactiva, la información de la empresa se conserva tal como está.
    </StatusMessage>
);
