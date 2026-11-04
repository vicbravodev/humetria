import { MessageSquareWarning } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { PublicChannel } from '@/types';

/**
 * The company's channel for complaints and reports, as the channel says it: the button to its own page (a full visit: that page
 * keeps no session nor cookies), what it takes and whether it accepts anonymous ones. It promises nothing more.
 */
export default function PublicChannelCard({
    channel,
}: {
    channel: PublicChannel;
}) {
    return (
        <section
            aria-label="Canal de quejas y denuncias"
            className="flex flex-col gap-3"
        >
            <p>
                {channel.anonymousAllowed
                    ? 'El canal permite presentar la queja o la denuncia de forma anónima.'
                    : 'El canal no recibe avisos anónimos: pide que se identifique.'}
            </p>
            {channel.categories.length > 0 && (
                <ul className="ml-6 list-disc text-muted-foreground">
                    {channel.categories.map((category) => (
                        <li key={category}>{category}</li>
                    ))}
                </ul>
            )}
            <div>
                <Button asChild size="lg">
                    <a href={channel.url}>
                        <MessageSquareWarning aria-hidden />
                        Presentar una queja o denuncia
                    </a>
                </Button>
            </div>
        </section>
    );
}
