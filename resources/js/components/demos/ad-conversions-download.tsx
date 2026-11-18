import { Button } from '@/components/ui/button';
import { adConversions, adConversionsIos } from '@/routes/demo-requests';

/** The CSVs of click conversions uploaded by hand in Google Ads (Goals → Conversions → Uploads): gclid, and iOS apart. */
export default function AdConversionsDownload() {
    return (
        <div
            role="group"
            aria-label="Conversiones para Google Ads"
            className="flex flex-wrap gap-2"
        >
            <Button variant="outline" asChild>
                <a href={adConversions.url()} download>
                    Conversiones para Google Ads (CSV)
                </a>
            </Button>
            <Button variant="outline" asChild>
                <a href={adConversionsIos.url()} download>
                    Conversiones de iOS (CSV)
                </a>
            </Button>
        </div>
    );
}
