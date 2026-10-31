import { Camera } from 'lucide-react';
import PhotoPicker from '@/components/telework/photo-picker';
import { Button } from '@/components/ui/button';
import { useEvidencePhotos } from '@/hooks/use-evidence-photos';
import type { PortalPhotoRequest } from '@/types';

/** Photos the Comisión asked for: what to photograph, a picker and one send (pantalla: Encuesta-Movil). */
export default function PortalPhotoRequestCard({
    request,
    token,
}: {
    request: PortalPhotoRequest;
    token: string;
}) {
    const form = useEvidencePhotos(token, request.id);

    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <Camera aria-hidden className="size-5" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">
                    La Comisión le pide fotos de su lugar de trabajo
                </p>
                <p className="text-base text-muted-foreground">
                    {request.workplace}
                </p>
                <p className="mt-1 text-base">{request.note}</p>
                <div className="mt-2 grid gap-3">
                    <PhotoPicker
                        id={`evidence-${request.id}`}
                        photos={form.photos}
                        onPhotos={form.setPhotos}
                    />
                    {form.error !== undefined && (
                        <p role="alert" className="text-base text-destructive">
                            {form.error}
                        </p>
                    )}
                    <Button
                        className="h-12 self-start text-base"
                        disabled={form.processing || form.photos.length === 0}
                        onClick={form.send}
                    >
                        Enviar fotos
                    </Button>
                </div>
            </div>
        </li>
    );
}
