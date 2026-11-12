import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** An account that pays no company yet. */
export default function AccountCompaniesEmpty() {
    return (
        <EmptyState
            icon={Building2}
            title="Esta cuenta aún no paga ninguna empresa"
        >
            Vincula una empresa para asignarle un plan y saber cuánto cuesta.
        </EmptyState>
    );
}
