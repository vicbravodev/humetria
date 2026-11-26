import { Badge, StackedTable, TableBody, TableCell, TableHead, TableHeader, TableRow } from 'humetria';

const zones = [
    { name: 'Acceso principal', radius: '80 m', center: '25.7781, −100.1883', status: 'Activa' },
    { name: 'Andén de carga', radius: '120 m', center: '25.7769, −100.1871', status: 'Activa' },
    { name: 'Estacionamiento norte', radius: '60 m', center: '25.7794, −100.1890', status: 'En pausa' },
];

export const Zones = () => (
    <div className="max-w-[720px] rounded-lg border">
        <StackedTable>
            <TableHeader>
                <TableRow>
                    <TableHead>Zona</TableHead>
                    <TableHead>Radio</TableHead>
                    <TableHead>Centro del círculo</TableHead>
                    <TableHead>Estado</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {zones.map((z) => (
                    <TableRow key={z.name}>
                        <TableCell className="font-medium max-md:w-full">{z.name}</TableCell>
                        <TableCell className="tabular-nums">{z.radius}</TableCell>
                        <TableCell className="text-muted-foreground tabular-nums">{z.center}</TableCell>
                        <TableCell>
                            <Badge variant={z.status === 'Activa' ? 'secondary' : 'outline'}>{z.status}</Badge>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    </div>
);
