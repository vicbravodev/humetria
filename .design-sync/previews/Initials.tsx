import { Initials } from 'humetria';

export const PersonAndCompany = () => (
    <div className="flex items-center gap-3">
        <Initials name="María Fernanda López" />
        <Initials name="Grupo Industrial Norteño" kind="company" />
    </div>
);

export const People = () => (
    <div className="flex items-center gap-3">
        <Initials name="Ana Gómez" />
        <Initials name="José Luis Ramírez" />
        <Initials name="Carmen Ortiz" />
        <Initials name="Diego Hernández" />
    </div>
);

export const Sizes = () => (
    <div className="flex items-center gap-3">
        <Initials name="Ana Gómez" className="size-6 text-[11px]" />
        <Initials name="Ana Gómez" />
        <Initials name="Ana Gómez" className="size-10 text-sm" />
        <Initials name="Logística del Bajío" kind="company" className="size-10 text-sm" />
    </div>
);

export const InARow = () => (
    <div className="flex items-center gap-3">
        <Initials name="María Fernanda López" />
        <span className="grid leading-tight">
            <span className="text-sm font-medium">María Fernanda López</span>
            <span className="text-caption text-muted-foreground">mlopez@empresa.mx</span>
        </span>
    </div>
);
