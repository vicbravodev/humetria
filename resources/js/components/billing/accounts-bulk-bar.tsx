import { router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import RecordPaymentDialog from '@/components/billing/record-payment-dialog';
import BulkBar from '@/components/humetria/bulk-bar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useToday } from '@/hooks/use-today';
import { store } from '@/routes/billing/reminders';

/**
 * What to do with the accounts ticked: remind them what they owe past due (each at most once a day) and, with one chosen,
 * record its payment, which belongs to one account. It sticks to the bottom of the table only while something is ticked.
 */
export default function AccountsBulkBar({
    chosen,
    owed,
    onDone,
}: {
    chosen: string[];
    /** What the chosen accounts owe together. */
    owed: string;
    onDone: () => void;
}) {
    const today = useToday();
    const [sending, setSending] = useState(false);
    const [only] = chosen;

    return (
        <BulkBar
            count={chosen.length}
            one="cuenta seleccionada"
            many="cuentas seleccionadas"
            detail={owed}
            onClear={onDone}
        >
            <Button
                disabled={sending}
                onClick={() =>
                    router.post(
                        store.url(),
                        { account_ids: chosen },
                        {
                            preserveScroll: true,
                            onStart: () => setSending(true),
                            onFinish: () => setSending(false),
                            onSuccess: onDone,
                        },
                    )
                }
            >
                {sending ? <Spinner /> : <Send aria-hidden />}
                Enviar recordatorio
            </Button>
            {chosen.length === 1 && only !== undefined && (
                <RecordPaymentDialog accountId={only} today={today} />
            )}
        </BulkBar>
    );
}
