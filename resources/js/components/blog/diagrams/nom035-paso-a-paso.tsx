import StepsDiagram from '@/components/blog/diagrams/steps-diagram';

const STEPS = [
    'La política de prevención, por escrito',
    'Identificar y canalizar con la Guía I',
    'Aplicar el cuestionario que corresponda',
    'Analizar los resultados sin promediarlos',
    'El Programa de intervención',
    'El informe de resultados',
    'Difundir y poner los resultados a consulta',
] as const;

/** The seven obligations of the NOM-035 in the order the article walks through them. */
export default function Nom035PasoAPaso() {
    return (
        <StepsDiagram
            title="Las siete obligaciones, en orden"
            source="Numerales 5, 7 y 8 de la NOM-035. El artículo explica cada una."
            steps={STEPS}
        />
    );
}
