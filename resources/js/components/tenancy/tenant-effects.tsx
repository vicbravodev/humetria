import type { Effect } from '@/lib/tenant-restriction';

/** What confirming does to the company, one fact per line, before the person decides. */
export default function TenantEffects({ effects }: { effects: Effect[] }) {
    return (
        <dl
            aria-label="Qué pasará"
            data-test="restriction-effects"
            className="grid gap-3 rounded-xl bg-muted px-4 py-3.5 text-sm"
        >
            {effects.map((effect) => (
                <div key={effect.label} className="grid gap-0.5">
                    <dt className="font-semibold">{effect.label}</dt>
                    <dd className="text-muted-foreground">{effect.text}</dd>
                </div>
            ))}
        </dl>
    );
}
