const KEYS = [
    { label: 'Horas ordinarias', tone: 'bg-primary' },
    { label: 'Horas extra', tone: 'bg-risk-medio' },
    { label: 'Día de más de 12 h', tone: 'bg-risk-muy-alto' },
    {
        label: 'Vacaciones o permiso',
        tone: 'border border-dashed border-muted-foreground',
    },
];

/** What the colors of `<WeekRibbon>` mean, and what its bars are; goes once under the list that shows ribbons. */
export default function WeekRibbonLegend() {
    return (
        <p className="flex flex-wrap items-center gap-x-5 gap-y-1 px-1 text-sm text-muted-foreground">
            {KEYS.map(({ label, tone }) => (
                <span key={label} className="flex items-center gap-1.5">
                    <i
                        aria-hidden
                        className={`size-2.5 rounded-[3px] ${tone}`}
                    />
                    {label}
                </span>
            ))}
            <span>
                Cada barra es un día, de lunes a domingo; el tope de la barra
                son 12 h.
            </span>
        </p>
    );
}
