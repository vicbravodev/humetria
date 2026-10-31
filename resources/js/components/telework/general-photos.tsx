import PhotoPicker from '@/components/telework/photo-picker';
import { GENERAL } from '@/lib/telework/answers';

/** Photos of the workplace as a whole, asked of whoever accepted the consent (Apéndice 3, criterio C-15). */
export default function GeneralPhotos({
    photos,
    onPhotos,
}: {
    photos: File[];
    onPhotos: (files: File[]) => void;
}) {
    return (
        <section className="grid gap-2 rounded-xl border bg-card p-4">
            <h2 className="text-xl font-semibold">
                Fotos del lugar (opcional)
            </h2>
            <p className="text-base text-muted-foreground">
                Si está de acuerdo, adjunte fotografías de su lugar de trabajo.
            </p>
            <PhotoPicker id={GENERAL} photos={photos} onPhotos={onPhotos} />
        </section>
    );
}
