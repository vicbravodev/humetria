import { WalletCards } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** The list without accounts, or with none that matches the search and the kind. */
export default function AccountsEmpty({ filtered }: { filtered: boolean }) {
    return filtered ? (
        <EmptyState icon={WalletCards} title="Ninguna cuenta coincide">
            Prueba con otro nombre, RFC o contacto, o con otro tipo de cuenta.
        </EmptyState>
    ) : (
        <EmptyState icon={WalletCards} title="Aún no hay cuentas">
            Crea la cuenta de quien paga (una empresa o un consultor) y
            vincúlale sus empresas.
        </EmptyState>
    );
}
