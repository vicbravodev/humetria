import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { show } from '@/routes/portal';

/** The way back to the home of the person's portal, from any of its pages: the portal is never a dead end. */
export default function PortalHomeLink({ token }: { token: string }) {
    return (
        <Link
            href={show.url({ token })}
            className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
        >
            <ArrowLeft aria-hidden className="size-4" />
            Volver a mi portal
        </Link>
    );
}
