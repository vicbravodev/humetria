import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { home } from '@/routes/worker';

/** The way back to the start of the person's pages, from any of them: the portal is never a dead end. */
export default function WorkerHomeLink({ slug }: { slug: string }) {
    return (
        <Link
            href={home.url(slug)}
            className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
        >
            <ArrowLeft aria-hidden className="size-4" />
            Volver a mi portal
        </Link>
    );
}
