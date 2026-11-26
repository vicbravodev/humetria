import { Button, SectionTabs } from 'humetria';
import { Printer } from 'lucide-react';

const SECTIONS = [
    { id: 'resumen', label: 'Resumen' },
    { id: 'categorias', label: 'Categorías' },
    { id: 'dominios', label: 'Dominios' },
    { id: 'areas', label: 'Por área' },
    { id: 'medidas', label: 'Medidas' },
];

export const ReportSections = () => (
    <SectionTabs label="Secciones del informe" sections={SECTIONS} className="top-0" />
);

export const WithActions = () => (
    <SectionTabs
        label="Secciones del reporte"
        sections={[
            { id: 'costo', label: 'Costo total' },
            { id: 'niveles', label: 'Salarios por nivel' },
            { id: 'prestaciones', label: 'Prestaciones' },
            { id: 'escenarios', label: 'Escenarios' },
        ]}
        className="top-0"
    >
        <Button variant="outline" size="sm">
            <Printer />
            Imprimir
        </Button>
    </SectionTabs>
);
