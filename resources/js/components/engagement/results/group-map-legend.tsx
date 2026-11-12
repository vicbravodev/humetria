import { Lock } from 'lucide-react';

const TIERS = [
    { label: 'Menos de 50 %', fill: 'bg-muted' },
    { label: '50 a 69 %', fill: 'bg-accent' },
    { label: '70 % o más', fill: 'bg-primary' },
];

/** What the fills of the map mean and why some groups are not there. */
export default function GroupMapLegend({ hasHidden }: { hasHidden: boolean }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-sm text-muted-foreground">
            <ul className="flex flex-wrap gap-x-4 gap-y-1">
                {TIERS.map((tier) => (
                    <li key={tier.label} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className={`size-3.5 rounded-[3px] border ${tier.fill}`}
                        />
                        {tier.label}
                    </li>
                ))}
            </ul>
            {hasHidden && (
                <p className="flex items-center gap-1.5">
                    <Lock aria-hidden className="size-4" />
                    Algunos grupos no se muestran para proteger el anonimato.
                </p>
            )}
        </div>
    );
}
