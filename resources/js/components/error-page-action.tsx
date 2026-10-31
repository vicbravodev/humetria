import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

/**
 * Where the error page leads: back to the panel when signed in, to the landing otherwise. Someone answering a survey
 * knows neither: they get their own link again, loaded afresh.
 */
export default function ErrorPageAction({ survey }: { survey: boolean }) {
    const page = usePage();

    if (survey) {
        return (
            <Button asChild>
                <a href={page.url}>Volver a intentar</a>
            </Button>
        );
    }

    return (
        <Button asChild>
            {page.props.auth.user === null ? (
                <Link href={home()}>Ir al inicio</Link>
            ) : (
                <Link href={dashboard()}>Volver al panel</Link>
            )}
        </Button>
    );
}
