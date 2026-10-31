import RiskLevel from '@/components/humetria/risk-level';
import type { RiskLevelInfo } from '@/types';

/** The five levels with the action each one needs (design system: RiskScale), from Nulo to Muy alto. */
export default function RiskScale({ levels }: { levels: RiskLevelInfo[] }) {
    return (
        <dl className="rounded-lg border bg-card">
            {levels.map((level) => (
                <div
                    key={level.value}
                    className="grid break-inside-avoid gap-x-6 gap-y-2 border-b px-6 py-[18px] last:border-b-0 sm:grid-cols-[170px_minmax(0,1fr)] sm:items-start"
                >
                    <dt>
                        <RiskLevel level={level.value} label={level.label} />
                    </dt>
                    <dd className="text-sm leading-[22px]">{level.action}</dd>
                </div>
            ))}
        </dl>
    );
}
