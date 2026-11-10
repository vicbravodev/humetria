import GrowBar from '@/components/landing/grow-bar';

const balances = [
    { who: 'Ana López', left: 8, of: 14 },
    { who: 'Jorge Ruiz', left: 3, of: 12 },
    { who: 'María Sánchez', left: 11, of: 16 },
] as const;

/** What each person has left of this year's vacation, with a request waiting for approval. */
export default function VisualLeave() {
    return (
        <div className="flex flex-col gap-4 card p-4">
            <p className="text-sm font-medium">
                Días de vacaciones disponibles
            </p>
            <ul className="flex flex-col gap-3">
                {balances.map((balance, index) => (
                    <li key={balance.who} className="flex flex-col gap-1.5">
                        <span className="flex justify-between gap-3 text-sm">
                            <span>{balance.who}</span>
                            <span className="font-medium tabular-nums">
                                {balance.left} de {balance.of}
                            </span>
                        </span>
                        <GrowBar
                            share={balance.left / balance.of}
                            delay={index * 0.12}
                        />
                    </li>
                ))}
            </ul>
            <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                <span className="font-medium">Solicitud de Jorge Ruiz</span>{' '}
                <span className="text-muted-foreground">
                    · 5 días, pendiente de su jefe
                </span>
            </p>
        </div>
    );
}
