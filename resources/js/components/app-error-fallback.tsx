import { CircleAlert, RotateCw } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import StatusMessage from '@/components/humetria/status-message';
import RequestIdNote from '@/components/request-id-note';
import { Button } from '@/components/ui/button';

/**
 * What shows instead of a blank screen when a page breaks while it renders (AppErrorBoundary): the error page's look,
 * a reload, and the request id to quote to support. Without "tú" or "usted": it can be the panel or the survey.
 */
export default function AppErrorFallback({
    requestId,
}: {
    requestId: string | null;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <header className="px-4 py-7 sm:px-10">
                <Logo className="h-[26px]" />
            </header>
            <main className="flex flex-1 items-center justify-center px-6 pt-6 pb-30">
                <StatusMessage
                    icon={CircleAlert}
                    title="Algo salió mal"
                    actions={
                        <Button onClick={() => window.location.reload()}>
                            <RotateCw aria-hidden />
                            Recargar la página
                        </Button>
                    }
                >
                    No se pudo mostrar esta página. Ya quedó registrado;
                    recargarla suele resolverlo.
                    <RequestIdNote requestId={requestId} />
                </StatusMessage>
            </main>
        </div>
    );
}
