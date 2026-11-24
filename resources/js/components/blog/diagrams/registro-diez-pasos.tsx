import StepsDiagram from '@/components/blog/diagrams/steps-diagram';

const STEPS = [
    'Actualiza los horarios a 46 horas',
    'Define quién registra y cómo',
    'Documenta el acuerdo con cada persona',
    'Pide la conformidad periódica',
    'Corrige sin borrar el original',
    'Decide cuánto tiempo conservas el registro',
    'Ten listo el reporte para la autoridad',
    'Revisa las horas extra con los topes de 2027',
    'Comunícalo a la plantilla',
    'Vigila las disposiciones de la STPS',
] as const;

/** The ten steps of the list to be ready for the electronic record of 2027, as the article numbers them. */
export default function RegistroDiezPasos() {
    return (
        <StepsDiagram
            title="Diez pasos para llegar a enero con el registro listo"
            source="Artículo 132, fracción XXXIV de la Ley Federal del Trabajo. El artículo desarrolla cada paso."
            steps={STEPS}
        />
    );
}
