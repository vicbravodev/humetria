import GrowBar from '@/components/landing/grow-bar';

const scenarios = [
    { name: 'Empresa · 4 %', millions: 4.1 },
    { name: 'Punto medio · 5.5 %', millions: 5.6 },
    { name: 'Sindicato · 7 %', millions: 7.2 },
] as const;

const MOST = 7.2;

/** Three raise proposals side by side, with what each costs the first year. */
export default function VisualCompensation() {
    return (
        <div className="flex flex-col gap-4 card p-4">
            <p className="text-sm font-medium">Costo del primer año</p>
            <ul className="flex flex-col gap-3">
                {scenarios.map((scenario, index) => (
                    <li key={scenario.name} className="flex flex-col gap-1.5">
                        <span className="flex justify-between gap-3 text-sm">
                            <span>{scenario.name}</span>
                            <span className="font-medium tabular-nums">
                                ${scenario.millions} M
                            </span>
                        </span>
                        <GrowBar
                            share={scenario.millions / MOST}
                            delay={index * 0.12}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
