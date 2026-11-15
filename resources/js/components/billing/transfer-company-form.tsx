import { Form } from '@inertiajs/react';
import TransferFields from '@/components/billing/transfer-fields';
import TransferPreviewPanel from '@/components/billing/transfer-preview-panel';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useLivePreview } from '@/hooks/use-live-preview';
import { nextCut, parseTransferPreview } from '@/lib/billing-transfer';
import { preview, store } from '@/routes/billing/transfers';
import type { AccountCompanyRow } from '@/types';

/** The move of a company, with what it would do beside the fields, asked again as they change: nobody confirms what they have not seen. */
export default function TransferCompanyForm({
    company,
    today,
    onDone,
}: {
    company: AccountCompanyRow;
    today: string;
    onDone: () => void;
}) {
    const live = useLivePreview(
        preview.url(company.company_id),
        parseTransferPreview,
        false,
    );

    return (
        <Form
            {...store.form(company.company_id)}
            noValidate
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            onChange={live.schedule}
            onClick={live.schedule}
            onKeyUp={live.schedule}
        >
            {({ processing, errors }) => (
                <div ref={live.anchorRef} className="grid gap-6">
                    <div className="grid items-start gap-6 md:grid-cols-[minmax(0,1fr)_320px]">
                        <TransferFields
                            effectiveOn={nextCut(today)}
                            errors={{ ...live.errors, ...errors }}
                        />
                        <TransferPreviewPanel preview={live.result} />
                    </div>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Trasladar empresa
                        </Button>
                    </DialogFooter>
                </div>
            )}
        </Form>
    );
}
