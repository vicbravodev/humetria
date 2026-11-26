import { Separator } from 'humetria';

export const BetweenSections = () => (
    <div className="grid gap-4 max-w-sm">
        <div className="grid gap-1">
            <p className="text-base font-semibold">Planta Apodaca</p>
            <p className="text-sm text-muted-foreground">186 personas · Guías I y III</p>
        </div>
        <Separator />
        <div className="grid gap-1">
            <p className="text-base font-semibold">Bodega Escobedo</p>
            <p className="text-sm text-muted-foreground">42 personas · Guías I y II</p>
        </div>
    </div>
);

export const Vertical = () => (
    <div className="flex items-center gap-3 text-sm text-muted-foreground" style={{ height: 20 }}>
        <span>142 de 186 contestaron</span>
        <Separator orientation="vertical" />
        <span>Cierra el 14 de diciembre</span>
        <Separator orientation="vertical" />
        <span>Guía III</span>
    </div>
);
