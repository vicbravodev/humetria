import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ItemSection } from '@/types';

const OPTIONS = [
    'Siempre',
    'Casi siempre',
    'Algunas veces',
    'Casi nunca',
    'Nunca',
];

/**
 * How each question of a section was answered, counted by option. A question answered by fewer than five (those behind
 * a yes/no gate are answered by some) shows no counts.
 */
export default function ItemsTable({ section }: { section: ItemSection }) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Respuestas por pregunta: {section.instruction}
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Pregunta</TableHead>
                    {OPTIONS.map((option) => (
                        <TableHead key={option} className="text-right">
                            {option}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {section.items.map((item) => (
                    <TableRow key={item.number}>
                        <TableCell className="min-w-64 whitespace-normal">
                            <span className="text-muted-foreground tabular-nums">
                                {item.number}.
                            </span>{' '}
                            {item.text}
                        </TableCell>
                        {item.counts === null ? (
                            <TableCell
                                colSpan={OPTIONS.length}
                                className="text-right text-sm text-muted-foreground"
                            >
                                {item.answered} respuestas: menos de 5, no se
                                muestra
                            </TableCell>
                        ) : (
                            item.counts.map((count, option) => (
                                <TableCell
                                    key={option}
                                    className="text-right tabular-nums"
                                >
                                    {count}
                                </TableCell>
                            ))
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
