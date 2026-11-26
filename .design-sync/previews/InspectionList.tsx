import { InspectionItem, InspectionList } from 'humetria';

export const Nom037Missing = () => (
    <InspectionList id="missing" title="Te falta" count={3} applicable={15} empty="No te falta nada por resolver.">
        <InspectionItem
            primary
            item={{ numeral: '5.5', title: 'Cada lugar revisado y validado por la Comisión', detail: '7 de 12 lugares con validación vigente de la Comisión. 1 lugar no apto.', state: 'partial', href: '#listas', action: 'Ver las listas por revisar' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: '5.10', title: 'Regreso a la oficina', detail: 'Aún no subes este documento.', state: 'missing', href: '#documentos', action: 'Ver los documentos de apoyo' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: '5.9 y 8', title: 'Capacitación del último año', detail: '3 personas sin capacitación vigente', state: 'partial', href: '#capacitaciones', action: 'Ver capacitaciones' }}
        />
    </InspectionList>
);

export const Nom037Ready = () => (
    <InspectionList id="ready" title="Lo puedes mostrar" count={3} applicable={15} empty="Todavía no tienes nada que mostrar. Empieza por lo que te falta.">
        <InspectionItem
            primary={false}
            item={{ numeral: '5.1', title: 'Lista de personas en teletrabajo', detail: null, state: 'done', href: '#personas', action: 'Ver personas' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: '5.2', title: 'Política de Teletrabajo completa y leída', detail: null, state: 'done', href: '#documentos', action: 'Ver documentos' }}
        />
        <InspectionItem
            primary={false}
            item={{ numeral: '5.4 y 7', title: 'Lista de verificación con todos los temas', detail: null, state: 'done', href: '#lista', action: 'Ver la lista' }}
        />
    </InspectionList>
);

export const NothingMissing = () => (
    <InspectionList id="missing-empty" title="Te falta" count={0} applicable={13} empty="No te falta nada por resolver.">
        {null}
    </InspectionList>
);

export const NothingReady = () => (
    <InspectionList id="ready-empty" title="Lo puedes mostrar" count={0} applicable={13} empty="Todavía no tienes nada que mostrar. Empieza por lo que te falta.">
        {null}
    </InspectionList>
);
