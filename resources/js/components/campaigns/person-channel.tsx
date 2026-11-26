import { Mail, MailX, MessageCircle } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CampaignPerson } from '@/types';

const LOOKS: Record<
    'mail' | 'whatsapp' | 'none',
    { icon: LucideIcon; label: string }
> = {
    mail: { icon: Mail, label: 'Correo' },
    whatsapp: { icon: MessageCircle, label: 'WhatsApp' },
    none: { icon: MailX, label: 'Sin contacto' },
};

/** The channel a person's link went through (or would go), with its icon. */
export default function PersonChannel({
    channel,
}: {
    channel: CampaignPerson['channel'];
}) {
    const { icon: Icon, label } = LOOKS[channel ?? 'none'];

    return (
        <span
            data-test="person-channel"
            className="flex items-center gap-1.5 text-sm"
        >
            <Icon aria-hidden="true" className="size-4 text-muted-foreground" />
            {label}
        </span>
    );
}
