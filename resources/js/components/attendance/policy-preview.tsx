import { Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { markCases } from '@/lib/attendance/policy-preview';
import type { MarkLevel } from '@/lib/attendance/policy-preview';
import type { AttendancePolicy } from '@/types';

const TONES: Record<
    MarkLevel,
    'success' | 'warning' | 'destructive' | 'secondary'
> = {
    strong: 'success',
    medium: 'warning',
    basic: 'secondary',
    rejected: 'destructive',
};

/** «Así queda una marca» (board A6): five ways of marking and what each one becomes under the policy being edited. */
export default function PolicyPreview({
    policy,
}: {
    policy: AttendancePolicy;
}) {
    return (
        <aside
            aria-labelledby="policy-preview"
            className="flex flex-col gap-4 card p-6 lg:sticky lg:top-6"
        >
            <div className="flex flex-col gap-1">
                <h2 id="policy-preview" className="text-lg font-semibold">
                    Así queda una marca
                </h2>
                <p className="text-sm text-muted-foreground">
                    Con lo que elegiste, esto pasa en cada caso. Cambia una
                    opción y míralo.
                </p>
            </div>
            <ul className="flex flex-col divide-y">
                {markCases(policy).map(({ id, text, outcome }) => (
                    <li key={id} className="flex flex-col gap-1.5 py-3">
                        <span className="text-sm font-medium">{text}</span>
                        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <Badge variant={TONES[outcome.level]}>
                                {outcome.label}
                            </Badge>
                            <span className="text-sm text-muted-foreground">
                                {outcome.note}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
            <p className="flex items-start gap-2 rounded-lg bg-accent px-3.5 py-3 text-sm text-pretty">
                <Info aria-hidden className="mt-0.5 size-4 shrink-0" />
                Cada cambio queda en la bitácora con quién lo hizo y cuándo.
            </p>
        </aside>
    );
}
