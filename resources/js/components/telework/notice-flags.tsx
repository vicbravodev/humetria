import { Badge } from '@/components/ui/badge';
import type { NoticeRow } from '@/types';

/** What sets a notice apart: a severe traumatic event, a temporary change, the new address. */
export default function NoticeFlags({ row }: { row: NoticeRow }) {
    return (
        <>
            {(row.traumaticEvent || row.temporary) && (
                <ul
                    aria-label="Marcas del aviso"
                    className="flex flex-wrap gap-2"
                >
                    {row.traumaticEvent && (
                        <li>
                            <Badge variant="destructive">
                                Acontecimiento traumático severo
                            </Badge>
                        </li>
                    )}
                    {row.temporary && (
                        <li>
                            <Badge variant="secondary">Cambio temporal</Badge>
                        </li>
                    )}
                </ul>
            )}
            {row.newAddress !== null && (
                <p className="text-[15px] leading-[22px] text-muted-foreground">
                    Nuevo domicilio: {row.newAddress}
                </p>
            )}
        </>
    );
}
