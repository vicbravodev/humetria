import { Link } from '@inertiajs/react';
import { Mail, MessageCircle } from 'lucide-react';
import ContactChannelRow from '@/components/campaigns/contact-channel-row';
import ReviewStep from '@/components/campaigns/review-step';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import type { DraftContact } from '@/types';

/**
 * How the links will reach people: each person by one channel, email or WhatsApp, as the company put them first. Who has
 * neither is said first, with the way to add one.
 */
export default function ContactStep({
    contact,
    employeesUrl,
}: {
    contact: DraftContact;
    /** The people of the work center, where an email or a phone is added. */
    employeesUrl: string;
}) {
    const emails = contact.reachable - contact.whatsapp;

    return (
        <ReviewStep title="Contacto">
            {contact.unreachable > 0 && (
                <Notice
                    action={
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="border-current bg-transparent text-inherit hover:bg-transparent hover:text-inherit"
                        >
                            <Link href={employeesUrl}>Agregar contacto</Link>
                        </Button>
                    }
                >
                    <strong>
                        {contact.unreachable === 1
                            ? '1 persona no tiene correo ni celular.'
                            : `${contact.unreachable} personas no tienen correo ni celular.`}
                    </strong>{' '}
                    No recibirán su enlace hasta que lo agregues.
                </Notice>
            )}
            <div data-test="contact-channels" className="flex flex-col gap-2">
                {(emails > 0 || contact.whatsapp === 0) && (
                    <ContactChannelRow
                        icon={Mail}
                        label="Correo"
                        people={emails}
                    />
                )}
                {contact.whatsapp > 0 && (
                    <ContactChannelRow
                        icon={MessageCircle}
                        label="WhatsApp"
                        people={contact.whatsapp}
                    />
                )}
            </div>
        </ReviewStep>
    );
}
