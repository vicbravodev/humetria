import { Button } from '@/components/ui/button';
import { adConversions } from '@/routes/demo-requests';

/** The CSV of click conversions that is uploaded by hand in Google Ads (Goals → Conversions → Uploads). */
export default function AdConversionsDownload() {
    return (
        <Button variant="outline" asChild>
            <a href={adConversions.url()} download>
                Conversiones para Google Ads (CSV)
            </a>
        </Button>
    );
}
