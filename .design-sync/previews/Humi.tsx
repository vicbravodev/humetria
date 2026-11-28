import { Humi } from 'humetria';

// The four expressions say what Humi is doing: hola (rest), escucha (the person writes), calcula (it looks things up), listo.
export const Expressions = () => (
    <div className="flex items-end gap-6">
        {(['hola', 'escucha', 'calcula', 'listo'] as const).map((expression) => (
            <figure key={expression} className="flex flex-col items-center gap-2">
                <Humi expression={expression} size={96} />
                <figcaption className="text-sm text-muted-foreground">{expression}</figcaption>
            </figure>
        ))}
    </div>
);

// The ticks above its head are the risk scale: an answer about a risk lights as many as it has.
export const RiskTicks = () => (
    <div className="flex items-end gap-6">
        {[1, 3, 5].map((ticks) => (
            <figure key={ticks} className="flex flex-col items-center gap-2">
                <Humi expression="listo" ticks={ticks} size={96} />
                <figcaption className="text-sm text-muted-foreground tabular-nums">{ticks} de 5</figcaption>
            </figure>
        ))}
    </div>
);

export const Small = () => (
    <div className="flex items-center gap-4">
        <Humi full={false} size={48} />
        <Humi full={false} size={32} expression="escucha" />
        <Humi full={false} size={24} expression="calcula" />
    </div>
);
