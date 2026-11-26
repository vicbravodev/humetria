import { LevelBar, LevelLegend, TooltipProvider } from 'humetria';

const levels = [
    { value: 'null' as const, label: 'Nulo', action: '' },
    { value: 'low' as const, label: 'Bajo', action: '' },
    { value: 'medium' as const, label: 'Medio', action: '' },
    { value: 'high' as const, label: 'Alto', action: '' },
    { value: 'very_high' as const, label: 'Muy alto', action: '' },
];

export const FinalScore = () => (
    <TooltipProvider>
        <div className="flex max-w-[560px] flex-col gap-4">
            <p className="text-[22px] leading-8 font-medium tracking-[-0.01em] text-pretty">
                3 de cada 10 cuestionarios salieron en riesgo alto o muy alto. El dominio más alto es Carga de trabajo.
            </p>
            <LevelBar
                counts={[18, 41, 52, 37, 16]}
                levels={levels}
                label="Cuestionarios por nivel de riesgo"
                className="h-3.5 rounded-full"
            />
            <LevelLegend levels={levels} counts={[18, 41, 52, 37, 16]} />
            <p className="text-caption text-muted-foreground">
                Cada cuestionario se califica por sí solo; no se promedian.
            </p>
        </div>
    </TooltipProvider>
);

export const RowHeight = () => (
    <TooltipProvider>
        <div className="flex max-w-[560px] flex-col gap-3">
            {[
                { name: 'Ambiente de trabajo', counts: [22, 61, 49, 23, 9] },
                { name: 'Factores propios de la actividad', counts: [9, 34, 55, 44, 22] },
                { name: 'Organización del tiempo de trabajo', counts: [14, 40, 58, 35, 17] },
            ].map((group) => (
                <div key={group.name} className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium">{group.name}</span>
                    <LevelBar counts={group.counts} levels={levels} label={group.name} />
                </div>
            ))}
            <LevelLegend levels={levels} />
        </div>
    </TooltipProvider>
);

export const MissingLevels = () => (
    <TooltipProvider>
        <div className="flex max-w-[560px] flex-col gap-3">
            <span className="text-sm font-medium">Almacén Escobedo · 7 cuestionarios</span>
            <LevelBar counts={[0, 4, 3, 0, 0]} levels={levels} label="Almacén Escobedo" />
            <LevelLegend levels={levels} counts={[0, 4, 3, 0, 0]} />
        </div>
    </TooltipProvider>
);
