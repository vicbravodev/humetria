import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { RoleMatrix as Matrix } from '@/types';

/**
 * «Qué puede hacer cada quien» (board V3-Roles): each role a column, as the permissions really are. «Sí» goes in the accent;
 * the table scrolls sideways inside its card on a phone.
 */
export default function RoleMatrix({ matrix }: { matrix: Matrix }) {
    return (
        <section aria-labelledby="role-matrix" className="overflow-hidden card">
            <h2
                id="role-matrix"
                className="border-b px-5 py-4 text-lg font-semibold"
            >
                Qué puede hacer cada quien
            </h2>
            <Table className="text-sm">
                <TableCaption className="sr-only">
                    Qué puede hacer cada rol en Humetría
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead className="whitespace-normal">
                            Puede
                        </TableHead>
                        {matrix.columns.map((column) => (
                            <TableHead
                                key={column}
                                className="min-w-24 whitespace-normal"
                            >
                                {column}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {matrix.rows.map((row) => (
                        <TableRow key={row.label}>
                            <TableCell className="min-w-36 font-medium whitespace-normal">
                                {row.label}
                            </TableCell>
                            {row.cells.map((cell, index) => (
                                <TableCell
                                    key={matrix.columns[index] ?? index}
                                    className={cn(
                                        'whitespace-normal',
                                        cell === 'Sí'
                                            ? 'font-semibold text-primary'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {cell}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <p className="border-t px-5 py-4 text-sm text-muted-foreground">
                Cada vez que alguien abre datos confidenciales, queda registrado
                quién y cuándo. Quien atiende quejas solo ve los casos que le
                designan, y nadie ve una queja en su contra.
            </p>
        </section>
    );
}
