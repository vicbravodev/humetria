import { Badge, Button, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from 'humetria';
import { Ellipsis } from 'lucide-react';

const people = [
    { name: 'Mariana López Treviño', area: 'Producción', boss: 'L. Garza', center: 'Planta Apodaca', status: 'Contestó', variant: 'success' as const },
    { name: 'Jorge Salinas Ruiz', area: 'Almacén', boss: 'L. Garza', center: 'Planta Apodaca', status: 'Pendiente', variant: 'secondary' as const },
    { name: 'Ana Cecilia Villarreal', area: 'Recursos Humanos', boss: 'R. Cantú', center: 'Oficinas Monterrey', status: 'Sin contacto', variant: 'warning' as const },
];

export const Employees = () => (
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>Persona</TableHead>
                <TableHead>Centro</TableHead>
                <TableHead>Evaluación</TableHead>
                <TableHead>
                    <span className="sr-only">Acciones</span>
                </TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            {people.map((p) => (
                <TableRow key={p.name}>
                    <TableCell>
                        <div className="font-medium">{p.name}</div>
                        <div className="text-sm text-muted-foreground">
                            {p.area} · Supervisa: {p.boss}
                        </div>
                    </TableCell>
                    <TableCell>{p.center}</TableCell>
                    <TableCell>
                        <Badge variant={p.variant}>{p.status}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                        <Button variant="ghost" size="icon" aria-label={`Acciones de ${p.name}`}>
                            <Ellipsis className="size-5" />
                        </Button>
                    </TableCell>
                </TableRow>
            ))}
        </TableBody>
    </Table>
);

export const NumericColumns = () => (
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>Centro de trabajo</TableHead>
                <TableHead className="text-right">Personas</TableHead>
                <TableHead className="text-right">Contestaron</TableHead>
                <TableHead>Estado</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow>
                <TableCell>Planta Apodaca</TableCell>
                <TableCell className="text-right tabular-nums">186</TableCell>
                <TableCell className="text-right tabular-nums">142</TableCell>
                <TableCell>
                    <Badge>En curso</Badge>
                </TableCell>
            </TableRow>
            <TableRow>
                <TableCell>Oficinas Monterrey</TableCell>
                <TableCell className="text-right tabular-nums">38</TableCell>
                <TableCell className="text-right tabular-nums">38</TableCell>
                <TableCell>
                    <Badge variant="secondary">Cerrada</Badge>
                </TableCell>
            </TableRow>
            <TableRow>
                <TableCell>Bodega Escobedo</TableCell>
                <TableCell className="text-right tabular-nums">12</TableCell>
                <TableCell className="text-right tabular-nums">0</TableCell>
                <TableCell>
                    <Badge variant="secondary">Borrador</Badge>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
);

export const Empty = () => (
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>Día festivo</TableHead>
                <TableHead>Fecha</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow>
                <TableCell colSpan={2} className="text-center text-muted-foreground">
                    Ningún día festivo coincide con «navidad».
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
);
