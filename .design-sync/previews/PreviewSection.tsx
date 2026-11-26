import { Button, DocumentPreview, PreviewSection } from 'humetria';
import { PenLine } from 'lucide-react';

const SECTIONS = [
    {
        key: 'a',
        heading: '1. Prevención de factores de riesgo psicosocial',
        text: 'Planta Apodaca identifica y analiza los factores de riesgo psicosocial cada dos años con la Guía III.\n\nLos resultados se comparten con todo el personal y guían el programa de intervención.',
        base: false,
        edited: false,
    },
    {
        key: 'b',
        heading: '2. Prevención de la violencia laboral',
        text: 'Cualquier persona puede reportar acoso con la Gerencia de Recursos Humanos o en el canal de denuncias, de forma anónima.',
        base: false,
        edited: false,
    },
    { key: 'c', heading: '3. Entorno organizacional favorable', text: null, base: false, edited: false },
];

export const InTheDocument = () => (
    <div className="max-w-xl">
        <DocumentPreview owner="Manufacturas del Norte, S.A. de C.V." title="Política de prevención de riesgos psicosociales">
            {SECTIONS.map((section) => (
                <PreviewSection key={section.key} section={section} current={section.key === 'b'} />
            ))}
        </DocumentPreview>
    </div>
);

export const Current = () => (
    <div className="max-w-xl" style={{ paddingInline: 12 }}>
        <PreviewSection section={SECTIONS[1]} current />
    </div>
);

export const Waiting = () => (
    <div className="max-w-xl" style={{ paddingInline: 12 }}>
        <PreviewSection section={SECTIONS[2]} current={false} />
    </div>
);

export const WithRewrite = () => (
    <div className="max-w-xl" style={{ paddingInline: 12 }}>
    <PreviewSection section={SECTIONS[0]} current={false}>
        <div>
            <Button variant="ghost" size="sm">
                <PenLine aria-hidden />
                Reescribir
            </Button>
        </div>
    </PreviewSection>
    </div>
);
