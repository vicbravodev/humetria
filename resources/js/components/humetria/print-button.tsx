import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Prints the report, or saves it as a PDF from the browser's print dialog. */
export default function PrintButton() {
    return (
        <Button type="button" onClick={() => window.print()}>
            <Printer aria-hidden="true" />
            Imprimir o guardar PDF
        </Button>
    );
}
