import { ArcMeter } from 'humetria';

export const SurveyProgress = () => (
    <div className="flex flex-col items-center gap-1">
        <ArcMeter value={142} max={186} label="142 de 186 contestaron" showPercent />
        <p className="text-sm text-muted-foreground">142 de 186 contestaron</p>
    </div>
);

export const Sizes = () => (
    <div className="flex items-end gap-6">
        <ArcMeter value={18} max={60} label="18 de 60 contestaron" size="sm" />
        <ArcMeter value={18} max={60} label="18 de 60 contestaron" size="md" showPercent />
        <ArcMeter value={18} max={60} label="18 de 60 contestaron" size="lg" showPercent />
    </div>
);

export const WithCount = () => (
    <div className="flex flex-col items-center gap-4 card p-6 text-center" style={{ width: 320 }}>
        <ArcMeter value={8} max={13} label="8 de 13 requisitos cumplen">
            <span className="text-4xl leading-10 font-semibold tabular-nums">8</span>
            <span className="text-meta text-muted-foreground">de 13 cumplen</span>
        </ArcMeter>
        <dl className="grid w-full grid-cols-3 gap-3 border-t pt-4 text-left text-sm">
            <div>
                <dt className="text-muted-foreground">Con aviso</dt>
                <dd className="font-semibold tabular-nums">2</dd>
            </div>
            <div>
                <dt className="text-muted-foreground">Por resolver</dt>
                <dd className="font-semibold tabular-nums">1</dd>
            </div>
            <div>
                <dt className="text-muted-foreground">Sin registros</dt>
                <dd className="font-semibold tabular-nums">2</dd>
            </div>
        </dl>
    </div>
);

export const EmptyAndFull = () => (
    <div className="flex items-end gap-6">
        <ArcMeter value={0} max={186} label="Nadie ha contestado" showPercent />
        <ArcMeter value={186} max={186} label="186 de 186 contestaron" showPercent />
    </div>
);
