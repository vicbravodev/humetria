import { InspectionItem, InspectionList } from 'humetria';

export const Missing = () => (
    <InspectionList id="missing" title="Te falta" count={2} applicable={13} empty="No te falta nada por resolver.">
        <InspectionItem
            primary
            item={{ numeral: 'R2', title: 'Cada persona que trabaja de pie, evaluada', detail: '4 personas sin evaluar', state: 'missing', href: '#evaluar', action: 'Evaluar' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: 'R4', title: 'Asientos en cada puesto', detail: '2 puestos con asientos de menos o por reparar', state: 'partial', href: '#asientos', action: 'Registrar asientos' }}
        />
    </InspectionList>
);

export const Ready = () => (
    <InspectionList id="ready" title="Lo puedes mostrar" count={2} applicable={13} empty="">
        <InspectionItem
            primary={false}
            item={{ numeral: 'R1', title: 'Los puestos de pie, con su Figura 1', detail: null, state: 'done', href: '#puestos', action: 'Ver puestos' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: 'R13', title: 'La Comisión asignada al centro', detail: null, state: 'done', href: '#comision', action: 'Ver' }}
        />
    </InspectionList>
);

export const Unregistered = () => (
    <InspectionList id="unregistered" title="Sin registros" count={1} applicable={13} empty="">
        <InspectionItem
            primary={false}
            item={{ numeral: 'R7', title: 'Una medida para cada riesgo medio o alto', detail: 'Aún no hay evaluaciones firmadas', state: 'pending', href: '#plan', action: 'Registrar medida' }}
        />
    </InspectionList>
);

export const ViewerNoAction = () => (
    <InspectionList id="viewer" title="Te falta" count={1} applicable={15} empty="">
        <InspectionItem
            primary={false}
            item={{ numeral: '5.7', title: 'Equipo entregado a cada persona', detail: '9 de 12 personas con equipo confirmado', state: 'partial', href: null, action: null }}
        />
    </InspectionList>
);
