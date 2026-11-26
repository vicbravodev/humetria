import { DocumentPreview, PreviewSection } from 'humetria';

export const PolicyInProgress = () => (
    <div className="max-w-xl">
        <DocumentPreview owner="Aceros del Norte, S.A. de C.V." title="Política de Teletrabajo">
            <PreviewSection
                current={false}
                section={{
                    key: 'scope',
                    heading: 'A quién aplica',
                    text: 'Aplica a las personas de Oficinas Monterrey que trabajan desde su domicilio más del 40 % de su jornada, previo acuerdo por escrito.',
                    base: false,
                    edited: false,
                }}
            />
            <PreviewSection
                current
                section={{
                    key: 'disconnection',
                    heading: 'Derecho a la desconexión',
                    text: 'Fuera del horario de 9:00 a 18:00 nadie está obligado a contestar correos, llamadas ni mensajes de trabajo.',
                    base: false,
                    edited: false,
                }}
            />
            <PreviewSection
                current={false}
                section={{ key: 'return', heading: 'Regreso a la oficina', text: null, base: false, edited: false }}
            />
        </DocumentPreview>
    </div>
);

export const NothingAnswered = () => (
    <div className="max-w-xl">
        <DocumentPreview owner="Planta Apodaca" title="Política de prevención de riesgos psicosociales">
            <PreviewSection current section={{ key: 'commitment', heading: 'Compromiso de la dirección', text: null, base: false, edited: false }} />
            <PreviewSection current={false} section={{ key: 'violence', heading: 'Prevención de la violencia laboral', text: null, base: false, edited: false }} />
            <PreviewSection current={false} section={{ key: 'complaints', heading: 'Cómo presentar una queja', text: null, base: false, edited: false }} />
        </DocumentPreview>
    </div>
);
