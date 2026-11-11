import { Lock } from 'lucide-react';
import RiskLevel from '@/components/humetria/risk-level';
import type { PrivacyArea } from '@/components/landing/privacy-areas';

/** The categories of the chosen group, or the notice that hides it when it has fewer than 5 answers. */
export default function PrivacyResults({ area }: { area: PrivacyArea }) {
    if (area.categories.length === 0) {
        return (
            <div className="flex min-h-[212px] flex-col items-center justify-center gap-3 p-6 text-center">
                <span className="flex size-11 items-center justify-center rounded-full bg-accent text-primary">
                    <Lock aria-hidden className="size-5" />
                </span>
                <p className="font-medium">
                    Oculto: este grupo tiene menos de 5 respuestas.
                </p>
                <p className="max-w-[34ch] text-sm text-muted-foreground">
                    Ni RH ni Humi pueden verlo. Así nadie adivina quién contestó
                    qué.
                </p>
            </div>
        );
    }

    return (
        <ul className="flex flex-col">
            {area.categories.map((category) => (
                <li
                    key={category.name}
                    className="flex items-center justify-between gap-3 border-t px-[18px] py-[15px] text-meta leading-snug first:border-t-0"
                >
                    <span>{category.name}</span>
                    <RiskLevel level={category.level} label={category.label} />
                </li>
            ))}
        </ul>
    );
}
