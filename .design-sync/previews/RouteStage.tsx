import { Badge, RouteStage, RouteTask } from 'humetria';

const noDone = { summary: '', href: null, action: 'Ver' };

export const Nom037Route = () => (
    <ol aria-label="Etapas de la NOM-037" className="flex flex-col">
        <RouteStage
            stage={{ number: 1, title: 'Define las reglas del teletrabajo', text: '', state: 'done' }}
            done={{ summary: 'Hecho: política y documentos de apoyo vigentes', href: '#documentos', action: 'Ver documentos' }}
        />
        <RouteStage
            stage={{ number: 2, title: 'Registra a quién teletrabaja y dónde', text: '', state: 'done' }}
            done={{ summary: 'Hecho: cada persona con su lugar convenido', href: '#personas', action: 'Ver personas' }}
        />
        <RouteStage
            stage={{
                number: 3,
                title: 'Revisa que cada lugar sea seguro',
                text: 'Cada persona contesta una lista sobre su espacio y la Comisión la valida.',
                state: 'current',
            }}
            done={noDone}
        >
            <ul className="@container mt-2.5 overflow-hidden card">
                <RouteTask
                    title="Cada lugar revisado y validado por la Comisión"
                    detail="7 de 12 lugares con validación vigente de la Comisión."
                    refs="numeral 5.5"
                    done={false}
                    href="#listas"
                    action="Ver las listas por revisar"
                    primary
                />
                <RouteTask
                    title="Lista de verificación con todos los temas"
                    detail={null}
                    refs="numeral 5.4 y 7"
                    done
                    href="#lista"
                    action="Ver la lista"
                    primary={false}
                />
            </ul>
        </RouteStage>
        <RouteStage
            stage={{
                number: 4,
                title: 'Entrega equipo y capacita',
                text: 'El equipo que entregas a cada persona y su capacitación vigente.',
                state: 'pending',
            }}
            done={noDone}
        />
        <RouteStage
            stage={{
                number: 5,
                title: 'Atiende lo que avisan las personas',
                text: 'Cambios de domicilio, accidentes y casos de fuerza mayor llegan desde su portal. Revísalos en cuanto lleguen.',
                state: 'pending',
            }}
            done={noDone}
            badge={<Badge variant="destructive">1 accidente sin revisar</Badge>}
        />
    </ol>
);

export const SeatingStart = () => (
    <ol aria-label="Etapas de la Ley Silla" className="flex flex-col">
        <RouteStage
            stage={{
                number: 1,
                title: 'Identifica los puestos de pie',
                text: 'Los puestos donde las personas trabajan de pie y quién los ocupa.',
                state: 'current',
            }}
            done={noDone}
        >
            <ul className="@container mt-2.5 overflow-hidden card">
                <RouteTask
                    title="Los puestos de pie, con su Figura 1"
                    detail="Declara el sector y registra los puestos de pie"
                    refs="R1"
                    done={false}
                    href="#puestos"
                    action="Registrar puestos"
                    primary
                />
            </ul>
        </RouteStage>
        <RouteStage
            stage={{
                number: 2,
                title: 'Pon asientos donde hacen falta',
                text: 'Asientos en cada puesto que lo permite y áreas de descanso señalizadas.',
                state: 'pending',
            }}
            done={noDone}
        />
        <RouteStage
            stage={{
                number: 3,
                title: 'Evalúa a cada persona que trabaja de pie',
                text: 'La Comisión revisa cómo le afecta estar de pie a cada quien. Los datos del puesto ya están: solo confirmas lo de la persona.',
                state: 'pending',
            }}
            done={noDone}
        />
    </ol>
);

export const AllDone = () => (
    <ol aria-label="Etapas de la NOM-037" className="flex flex-col">
        <RouteStage
            stage={{ number: 5, title: 'Atiende lo que avisan las personas', text: '', state: 'done' }}
            done={{ summary: 'Hecho: ningún aviso de accidente por atender', href: '#avisos', action: 'Ver los avisos' }}
        />
        <RouteStage
            stage={{ number: 6, title: 'Ten listo tu expediente', text: '', state: 'done' }}
            done={{ summary: 'Hecho: tu expediente está completo', href: null, action: 'Ver' }}
        />
    </ol>
);
