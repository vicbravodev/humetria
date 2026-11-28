import { HumiAvatar } from 'humetria';

export const Sizes = () => (
    <div className="flex items-center gap-4">
        <HumiAvatar size={56} />
        <HumiAvatar />
        <HumiAvatar size={28} />
    </div>
);

export const Expressions = () => (
    <div className="flex items-center gap-4">
        {(['hola', 'escucha', 'calcula', 'listo'] as const).map((expression) => (
            <HumiAvatar key={expression} expression={expression} size={44} />
        ))}
    </div>
);

// Beside its answer, as it sits in Humi's panel.
export const BesideAnAnswer = () => (
    <div className="flex max-w-sm items-start gap-3">
        <HumiAvatar expression="listo" />
        <p className="rounded-lg bg-muted px-4 py-3 text-sm">
            Tu campaña de NOM-035 lleva 64 % de respuestas; faltan 9 días para cerrarla.
        </p>
    </div>
);
