import { Button, PrintButton } from 'humetria';
import { FileDown } from 'lucide-react';

export const Default = () => <PrintButton />;

export const OutlineShortLabel = () => <PrintButton variant="outline" label="Imprimir" />;

export const BesideDownload = () => (
    <div className="flex gap-2">
        <PrintButton variant="outline" label="Imprimir" />
        <Button>
            <FileDown aria-hidden />
            Descargar CSV
        </Button>
    </div>
);
