import { RISK_LEVELS } from '@/lib/results';
import type { RiskLevelValue } from '@/types';

// Written out whole so Tailwind finds each class.
const TONES: Record<
    RiskLevelValue,
    { fill: string; label: string; text: string }
> = {
    null: {
        fill: 'bg-risk-nulo',
        label: 'bg-risk-nulo-bg text-risk-nulo-fg',
        text: 'text-risk-nulo-fg',
    },
    low: {
        fill: 'bg-risk-bajo',
        label: 'bg-risk-bajo-bg text-risk-bajo-fg',
        text: 'text-risk-bajo-fg',
    },
    medium: {
        fill: 'bg-risk-medio',
        label: 'bg-risk-medio-bg text-risk-medio-fg',
        text: 'text-risk-medio-fg',
    },
    high: {
        fill: 'bg-risk-alto',
        label: 'bg-risk-alto-bg text-risk-alto-fg',
        text: 'text-risk-alto-fg',
    },
    very_high: {
        fill: 'bg-risk-muy-alto',
        label: 'bg-risk-muy-alto-bg text-risk-muy-alto-fg',
        text: 'text-risk-muy-alto-fg',
    },
};

/** The solid color of a level: a bar segment, a square, a legend swatch. */
export function riskFill(level: RiskLevelValue): string {
    return TONES[level].fill;
}

/** The fill of the level at a place, 0 Nulo to 4 Muy alto; none past it. */
export function riskFillAt(place: number): string {
    const level = RISK_LEVELS[place];

    return level === undefined ? '' : riskFill(level);
}

/** A level written on its soft fill, in the shade that reads on it. */
export function riskLabel(level: RiskLevelValue): string {
    return TONES[level].label;
}

/** Text in a level's readable shade: a share that crosses into Alto or Muy alto. */
export function riskText(level: RiskLevelValue): string {
    return TONES[level].text;
}
