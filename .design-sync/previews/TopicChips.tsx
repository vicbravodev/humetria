import { TopicChips } from 'humetria';

const TOPICS = [
    { key: 'a', label: 'Prevención de factores de riesgo psicosocial' },
    { key: 'b', label: 'Prevención de la violencia laboral' },
    { key: 'c', label: 'Promoción de un entorno organizacional favorable' },
];

export const Policy = () => (
    <TopicChips
        topics={[
            { ...TOPICS[0], state: 'done' },
            { ...TOPICS[1], state: 'base' },
            { ...TOPICS[2], state: 'open' },
        ]}
        label="Temas que pide el numeral 5.1"
        note="Los 3 temas del numeral 5.1. Los punteados ya tienen texto base que puedes editar."
    />
);

export const AllCovered = () => (
    <TopicChips
        topics={TOPICS.map((topic) => ({ ...topic, state: 'done' as const }))}
        label="Temas que pide el numeral 5.1"
        note="Los 3 temas del numeral 5.1 ya están cubiertos."
    />
);

export const NoneYet = () => (
    <TopicChips
        topics={TOPICS.map((topic) => ({ ...topic, state: 'open' as const }))}
        label="Temas que pide el numeral 5.1"
        note="Los 3 temas del numeral 5.1. Contesta las preguntas para cubrirlos."
    />
);
