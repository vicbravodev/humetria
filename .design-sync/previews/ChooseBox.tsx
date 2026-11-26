import { Badge, ChooseBox, Initials, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from 'humetria';

const PEOPLE = [
    { name: 'Ana López Garza', policy: 'Ley 2023 · 12 días', days: '9 de 12 días', chosen: true },
    { name: 'Jorge Treviño Salinas', policy: 'Ley 2023 · 14 días', days: '14 de 14 días', chosen: true },
    { name: 'María Fernanda Ruiz', policy: 'Ley 2023 · 12 días', days: '3 de 12 días', chosen: false },
];

export const InATable = () => (
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>
                    <ChooseBox checked={false} onChange={() => {}} name="Elegir a todas las personas" />
                </TableHead>
                <TableHead>Persona</TableHead>
                <TableHead>Saldo</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            {PEOPLE.map((person) => (
                <TableRow key={person.name}>
                    <TableCell>
                        <ChooseBox checked={person.chosen} onChange={() => {}} name={`Elegir a ${person.name}`} />
                    </TableCell>
                    <TableCell>
                        <div className="flex items-center gap-3">
                            <Initials name={person.name} />
                            <div>
                                <p className="font-medium">{person.name}</p>
                                <p className="text-caption text-muted-foreground">{person.policy}</p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell>{person.days}</TableCell>
                </TableRow>
            ))}
        </TableBody>
    </Table>
);

export const CheckedAndNot = () => (
    <div className="flex items-center gap-3">
        <ChooseBox checked onChange={() => {}} name="Elegir a Ana López Garza" />
        <ChooseBox checked={false} onChange={() => {}} name="Elegir a Jorge Treviño Salinas" />
        <Badge variant="secondary">2 personas</Badge>
    </div>
);
