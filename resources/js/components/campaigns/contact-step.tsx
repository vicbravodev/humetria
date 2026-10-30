import { Link } from '@inertiajs/react';
import { Check, Mail } from 'lucide-react';
import ReviewStep from '@/components/campaigns/review-step';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { peopleText } from '@/lib/evaluation';
import type { DraftContact } from '@/types';

/**
 * How the links will reach people: by email, the one channel there is. Who has no email is said first, with the way to
 * add it.
 */
export default function ContactStep({
    contact,
    employeesUrl,
}: {
    contact: DraftContact;
    /** The people of the work center, where an email is added. */
    employeesUrl: string;
}) {
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
                            <Link href={employeesUrl}>Agregar correo</Link>
                        </Button>
                    }
                >
                    <strong>
                        {contact.unreachable === 1
                            ? '1 persona no tiene correo.'
                            : `${contact.unreachable} personas no tienen correo.`}
                    </strong>{' '}
                    No recibirán su enlace hasta que lo agregues.
                </Notice>
            )}
            <div className="flex min-h-13 items-center gap-3 rounded-[10px] border-2 border-primary bg-accent px-3.5 py-2.5">
                <span className="inline-flex size-5 items-center justify-center rounded-[5px] bg-primary text-primary-foreground">
                    <Check
                        aria-hidden="true"
                        className="size-3.5"
                        strokeWidth={3}
                    />
                </span>
                <Mail
                    aria-hidden="true"
                    className="size-[18px] text-muted-foreground"
                />
                <span className="flex flex-col">
                    <span className="text-sm font-medium">Correo</span>
                    <span className="text-xs text-muted-foreground">
                        {peopleText(contact.reachable)}
                    </span>
                </span>
            </div>
        </ReviewStep>
    );
}
