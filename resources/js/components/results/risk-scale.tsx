import RiskLevel from '@/components/humetria/risk-level';
import type { RiskLevelInfo } from '@/types';

/** The five levels with the action each one needs (design system: RiskScale), from Nulo to Muy alto. */
export default function RiskScale({ levels }: { levels: RiskLevelInfo[] }) {
    return (
        <dl className="divide-y rounded-xl border bg-card">
            {levels.map((level) => (
                <div
                    key={level.value}
                    className="grid gap-2 px-4 py-3 sm:grid-cols-[13rem_1fr] sm:items-center"
                >
                    <dt>
                        <RiskLevel level={level.value} label={level.label} />
                    </dt>
                    <dd className="text-sm text-muted-foreground">
                        {level.action}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
