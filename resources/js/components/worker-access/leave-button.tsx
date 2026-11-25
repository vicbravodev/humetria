import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { forgetPerson } from '@/hooks/use-offline-store';
import { forgetPushHere } from '@/hooks/use-push-offer';

/**
 * «Salir»: closes the person's session on this device; the next one to open it sees the screen to enter, with no name. First this
 * phone gives its notices up (best effort, it never holds the person back) and forgets what it kept of them to mark without a
 * connection (tickets, pass, device key): the marks that wait stay, and no key is revoked at the server, so what the phone
 * already signed is still accepted.
 */
export default function LeaveButton({
    url,
    slug,
}: {
    url: string;
    slug: string;
}) {
    const [leaving, setLeaving] = useState(false);

    const leave = async () => {
        setLeaving(true);
        await forgetPushHere(slug);
        await forgetPerson(slug);
        router.post(url, {}, { onFinish: () => setLeaving(false) });
    };

    return (
        <Button
            variant="outline"
            size="lg"
            className="w-full text-base"
            disabled={leaving}
            onClick={leave}
        >
            <LogOut aria-hidden className="size-5" />
            Salir
        </Button>
    );
}
