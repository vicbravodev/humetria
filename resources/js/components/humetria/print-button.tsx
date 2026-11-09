import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Prints the report, or saves it as a PDF from the browser's print dialog. */
export default function PrintButton({
    variant,
    label = 'Imprimir o guardar PDF',
}: {
    variant?: 'outline';
    label?: string;
}) {
    return (
        <Button type="button" variant={variant} onClick={() => window.print()}>
            <Printer aria-hidden="true" />
            {label}
        </Button>
    );
}
