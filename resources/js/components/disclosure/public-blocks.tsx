import PublicDetails from '@/components/disclosure/public-details';
import type { PublicCenterProps } from '@/types';

/**
 * The rest of 5.7 on the page of the people, folded (board V4-Pagina-Personas): the measures against violence, what is done to
 * prevent, how to complain when there is no channel to lead to, and when to look for medical help.
 */
export default function PublicBlocks({
    blocks,
    measures,
    channelOpen,
}: {
    blocks: PublicCenterProps['blocks'];
    measures: PublicCenterProps['measures'];
    /** The channel leads the page: how to complain goes with it. */
    channelOpen: boolean;
}) {
    return (
        <>
            <PublicDetails
                title="Medidas contra la violencia laboral"
                block={blocks['b']}
                measures={measures['b'] ?? []}
            />
            <PublicDetails
                title="Lo que hacemos para prevenir"
                block={blocks['c']}
                measures={measures['c'] ?? []}
            />
            {!channelOpen && (
                <PublicDetails
                    title="Cómo presentar una queja"
                    block={blocks['d']}
                />
            )}
            <PublicDetails
                title="Cuándo buscar ayuda médica"
                block={blocks['f']}
            />
        </>
    );
}
