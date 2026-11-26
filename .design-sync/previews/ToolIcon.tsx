import { ToolIcon } from 'humetria';

const TOOLS = [
    { tool: 'nom_035', name: 'NOM-035' },
    { tool: 'nom_037', name: 'NOM-037' },
    { tool: 'ley_silla', name: 'Ley Silla' },
    { tool: 'attendance', name: 'Asistencia' },
    { tool: 'complaints', name: 'Denuncias' },
    { tool: 'engagement', name: 'Clima y pulso' },
    { tool: 'leave', name: 'Vacaciones' },
    { tool: 'org_chart', name: 'Organigrama' },
    { tool: 'compensation', name: 'Compensación' },
] as const;

export const AllTools = () => (
    <div className="grid grid-cols-3 gap-4">
        {TOOLS.map(({ tool, name }) => (
            <span key={tool} className="flex items-center gap-2 text-sm">
                <ToolIcon tool={tool} />
                {name}
            </span>
        ))}
    </div>
);

export const OnTile = () => (
    <div className="flex items-center gap-3">
        {TOOLS.slice(0, 4).map(({ tool }) => (
            <span key={tool} className="flex size-10 items-center justify-center rounded-lg bg-accent text-primary">
                <ToolIcon tool={tool} className="size-5" />
            </span>
        ))}
    </div>
);

export const InMenuItem = () => (
    <span className="flex items-start gap-3">
        <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-primary">
            <ToolIcon tool="nom_035" className="size-5" />
        </span>
        <span className="grid gap-0.5">
            <span className="font-semibold">NOM-035</span>
            <span className="text-caption text-muted-foreground">Riesgo psicosocial por centro de trabajo</span>
        </span>
    </span>
);
