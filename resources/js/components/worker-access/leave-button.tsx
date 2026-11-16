import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** «Salir»: closes the person's session on this device; the next one to open it sees the screen to enter, with no name. */
export default function LeaveButton({ url }: { url: string }) {
    return (
        <Button
            variant="outline"
            size="lg"
            className="w-full text-base"
            onClick={() => router.post(url)}
        >
            <LogOut aria-hidden className="size-5" />
            Salir
        </Button>
    );
}
