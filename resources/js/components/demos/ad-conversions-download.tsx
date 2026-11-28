import { ChevronDown, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { adConversions, adConversionsIos } from '@/routes/demo-requests';

/**
 * «Conversiones de anuncios» (tablero Demos): the CSVs of click conversions uploaded by hand in Google Ads (Goals →
 * Conversions → Uploads), gclid and iOS apart, behind one button.
 */
export default function AdConversionsDownload() {
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    Conversiones de anuncios
                    <ChevronDown aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href={adConversions.url()} download>
                        <Download aria-hidden />
                        Conversiones para Google Ads (CSV)
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={adConversionsIos.url()} download>
                        <Download aria-hidden />
                        Conversiones de iOS (CSV)
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
