import { InspectionDossier, InspectionItem, InspectionList } from 'humetria';

export const Default = () => (
    <div className="w-[360px]">
        <InspectionDossier href="#expediente" />
    </div>
);

export const BesideTheLists = () => (
    <div className="grid gap-6" style={{ gridTemplateColumns: 'minmax(0,1fr) 360px' }}>
        <div className="flex min-w-0 flex-col gap-6">
            <InspectionList id="missing" title="Te falta" count={2} applicable={13} empty="No te falta nada por resolver.">
                <InspectionItem
                    primary
                    item={{ numeral: 'R10', title: 'Reglamento interior firmado, depositado y repartido', detail: 'Falta depositarlo o repartirlo', state: 'partial', href: '#reglamento', action: 'Continuar el reglamento' }}
                />
                <InspectionItem
                    primary={false}
                    item={{ numeral: 'R12', title: 'Diagnóstico y programa de seguridad y salud', detail: 'Falta el documento; hay plantilla con lo que ya capturaste', state: 'missing', href: '#documentos', action: 'Registrar el documento' }}
                />
            </InspectionList>
            <InspectionList id="ready" title="Lo puedes mostrar" count={2} applicable={13} empty="">
                <InspectionItem
                    primary={false}
                    item={{ numeral: 'R1', title: 'Los puestos de pie, con su Figura 1', detail: null, state: 'done', href: '#puestos', action: 'Ver puestos' }}
                />
                <InspectionItem
                    primary={false}
                    item={{ numeral: 'R4', title: 'Asientos en cada puesto', detail: null, state: 'done', href: '#asientos', action: 'Ver asientos' }}
                />
            </InspectionList>
        </div>
        <InspectionDossier href="#expediente" />
    </div>
);
