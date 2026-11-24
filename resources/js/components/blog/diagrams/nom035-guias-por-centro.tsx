import DiagramFrame from '@/components/blog/diagrams/diagram-frame';

const CASES = [
    { size: 'Hasta 15', unit: 'trabajadores', guides: ['Guía I'] },
    { size: 'De 16 a 50', unit: 'trabajadores', guides: ['Guía I', 'Guía II'] },
    {
        size: 'Más de 50',
        unit: 'trabajadores',
        guides: ['Guía I', 'Guía III'],
    },
] as const;

/** Which guides a work center applies, by how many people work there: the rule of the numeral 2, as three columns. */
export default function Nom035GuiasPorCentro() {
    return (
        <DiagramFrame
            title="La Guía depende de las personas de cada centro de trabajo"
            source="Numerales 2 y 7.1 de la NOM-035. Se cuenta cada centro por separado, no la plantilla de la empresa."
        >
            <ul className="grid gap-3 sm:grid-cols-3">
                {CASES.map((item) => (
                    <li
                        key={item.size}
                        className="grid content-start gap-3 rounded-xl bg-family-soft p-4"
                    >
                        <p>
                            <span className="block text-2xl font-semibold tracking-[-0.02em]">
                                {item.size}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {item.unit}
                            </span>
                        </p>
                        <ul className="flex flex-wrap gap-2">
                            {item.guides.map((guide) => (
                                <li
                                    key={guide}
                                    className="rounded-full bg-family px-3 py-1 text-caption font-semibold text-background"
                                >
                                    {guide}
                                </li>
                            ))}
                        </ul>
                    </li>
                ))}
            </ul>
        </DiagramFrame>
    );
}
