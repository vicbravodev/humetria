import { StageBar } from 'humetria';

export const InRoute = () => (
    <ul className="flex max-w-[560px] flex-col">
        {[
            { name: 'Planta Apodaca', states: ['done', 'done', 'done', 'current', 'pending', 'pending'] as const, line: 'Evaluación en curso' },
            { name: 'Oficinas Monterrey', states: ['done', 'current', 'pending', 'pending', 'pending', 'pending'] as const, line: 'Falta invitar al personal' },
            { name: 'CEDIS Escobedo', states: ['done', 'done', 'done', 'done', 'done', 'done'] as const, line: 'Ruta completa' },
        ].map((center) => (
            <li
                key={center.name}
                className="grid items-center gap-x-4 py-1 text-sm"
                style={{ gridTemplateColumns: "minmax(140px,180px) 112px minmax(0,1fr)" }}
            >
                <span className="truncate font-medium">{center.name}</span>
                <StageBar states={[...center.states]} />
                <span className="text-muted-foreground">{center.line}</span>
            </li>
        ))}
    </ul>
);

export const States = () => (
    <div className="flex flex-col gap-3">
        <StageBar states={['current', 'pending', 'pending', 'pending', 'pending', 'pending']} />
        <StageBar states={['done', 'done', 'current', 'pending', 'pending', 'pending']} />
        <StageBar states={['done', 'done', 'done', 'done', 'done', 'done']} />
    </div>
);
