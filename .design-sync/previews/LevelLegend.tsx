import { LevelLegend } from 'humetria';

const levels = [
    { value: 'null' as const, label: 'Nulo', action: '' },
    { value: 'low' as const, label: 'Bajo', action: '' },
    { value: 'medium' as const, label: 'Medio', action: '' },
    { value: 'high' as const, label: 'Alto', action: '' },
    { value: 'very_high' as const, label: 'Muy alto', action: '' },
];

export const WithCounts = () => <LevelLegend levels={levels} counts={[18, 41, 52, 37, 16]} />;

export const NamesOnly = () => <LevelLegend levels={levels} />;

export const ThreeBuckets = () => (
    <LevelLegend
        levels={[
            { value: 'low', label: 'Nulo o bajo', action: '' },
            { value: 'medium', label: 'Medio', action: '' },
            { value: 'very_high', label: 'Alto o muy alto', action: '' },
        ]}
        counts={[24, 19, 11]}
    />
);
