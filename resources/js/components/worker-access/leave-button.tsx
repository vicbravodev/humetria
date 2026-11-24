import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { forgetPushHere } from '@/hooks/use-push-offer';

/**
 * «Salir»: closes the person's session on this device; the next one to open it sees the screen to enter, with no name. First this
 * phone gives its notices up (best effort, it never holds the person back).
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
