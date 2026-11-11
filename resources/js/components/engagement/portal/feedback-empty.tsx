import { Hourglass } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/**
 * The only screen for «no hay nada que mostrar»: it is the same whether the survey is still open, the group was too small
 * or the person has no team, so it never says which.
 */
export default function FeedbackEmpty() {
    return (
        <EmptyState
            icon={Hourglass}
            title="Todavía no hay resultados para mostrar"
        >
            Cuando haya resultados de su equipo los verá aquí, en este mismo
            enlace.
        </EmptyState>
    );
}
