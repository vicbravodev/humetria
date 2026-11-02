import HumiCardFrame from '@/components/assistant/humi-card-frame';
import LevelBar from '@/components/humetria/level-bar';
import type { RiskRow, ToolCard } from '@/lib/assistant/tool-cards';
import { RISK_LEVELS } from '@/lib/results';

const SHOWN = 8;

function levelsOf(row: RiskRow) {
    return RISK_LEVELS.map((value, index) => ({
        value,
        label: row.levels[index]?.label ?? value,
        action: '',
    }));
}

/** The people in each risk level of a closed evaluation, as the bar of the panel: never an average, never one level for a group. */
export default function HumiRiskCard({
    card,
}: {
    card: Extract<ToolCard, { render: 'risk_levels' }>;
}) {
    const left = card.rows.length - SHOWN;

    return (
        <HumiCardFrame title={card.title} note={card.note} link={card.link}>
            <ul className="flex flex-col gap-3">
                {card.rows.slice(0, SHOWN).map((row) => (
                    <li
                        key={`${row.kind}-${row.label}`}
                        className="flex flex-col gap-1.5"
                    >
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="font-medium">{row.label}</span>
                            {row.answered !== null && (
                                <span className="text-muted-foreground">
                                    {row.answered} personas
                                </span>
                            )}
                        </div>
                        <LevelBar
                            counts={row.levels.map((level) => level.count)}
                            levels={levelsOf(row)}
                            label={row.label}
                            className="h-3"
                        />
                    </li>
                ))}
            </ul>
            {left > 0 && (
                <p className="text-sm text-muted-foreground">
                    y {left} más en el panel.
                </p>
            )}
            {card.minGroup !== null && (
                <p className="text-caption text-muted-foreground">
                    Solo se muestran grupos de al menos {card.minGroup}{' '}
                    personas.
                </p>
            )}
        </HumiCardFrame>
    );
}
