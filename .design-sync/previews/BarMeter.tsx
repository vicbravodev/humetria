import { BarMeter, ProtectedCell, TooltipProvider } from 'humetria';

const topics = [
    { text: 'Liderazgo de mi jefe directo', favorable: 78, reference: 71 },
    { text: 'Reconocimiento', favorable: 54, reference: 71 },
    { text: 'Carga de trabajo', favorable: 46, reference: 71 },
    { text: 'Crecimiento profesional', favorable: 63, reference: 71 },
];

export const TopicBars = () => (
    <TooltipProvider>
        <div className="flex max-w-[520px] flex-col gap-5">
            {topics.map((t, index) => (
                <div key={t.text} className="flex flex-col gap-2">
                    <div className="flex items-baseline justify-between gap-4 text-sm">
                        <span>{t.text}</span>
                        <span className="whitespace-nowrap tabular-nums">
                            <strong className="text-base font-semibold">{t.favorable} %</strong>
                            <span className="ml-1.5 text-muted-foreground">
                                {t.favorable - t.reference > 0 ? '+' : '−'}
                                {Math.abs(t.favorable - t.reference)} pts
                            </span>
                        </span>
                    </div>
                    <BarMeter percent={t.favorable} tooltip={`${t.text}: ${t.favorable} %`} index={index} reference={t.reference} />
                </div>
            ))}
        </div>
    </TooltipProvider>
);

export const MutedVsPrimary = () => (
    <TooltipProvider>
        <div className="flex max-w-[520px] flex-col gap-3">
            <span className="text-sm">Participación · Ola de noviembre</span>
            <BarMeter percent={82} tooltip="Noviembre: 82 %" />
            <span className="text-sm text-muted-foreground">Ola de agosto</span>
            <BarMeter percent={69} tooltip="Agosto: 69 %" muted index={1} />
        </div>
    </TooltipProvider>
);

export const UnderMinimum = () => (
    <TooltipProvider>
        <div className="flex max-w-[520px] flex-col gap-5">
            <div className="flex flex-col gap-2">
                <div className="flex items-baseline justify-between gap-4 text-sm">
                    <span>Comunicación interna</span>
                    <strong className="text-base font-semibold tabular-nums">61 %</strong>
                </div>
                <BarMeter percent={61} tooltip="Comunicación interna: 61 %" />
            </div>
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <span>Equipo de mantenimiento</span>
                <ProtectedCell className="shrink-0" />
            </div>
        </div>
    </TooltipProvider>
);
