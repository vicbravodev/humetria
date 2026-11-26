import { ProtectedCell } from 'humetria';

export const Default = () => <ProtectedCell />;

export const InCard = () => (
    <div className="flex max-w-[520px] flex-col gap-3 card p-5">
        <div className="flex items-baseline justify-between gap-4">
            <span className="text-sm text-muted-foreground">Compromiso · Ventas</span>
            <strong className="text-xl font-semibold tabular-nums">72 %</strong>
        </div>
        <div className="flex items-baseline justify-between gap-4">
            <span className="text-sm text-muted-foreground">Compromiso · Mantenimiento</span>
            <ProtectedCell />
        </div>
        <p className="text-caption text-muted-foreground">
            Un área con menos de 5 respuestas no se muestra.
        </p>
    </div>
);
