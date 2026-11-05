import { Camera, X } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';

/** "Agregar foto": JPG or PNG from the camera or the gallery; the server re-encodes them without metadata. */
export default function PhotoPicker({
    id,
    photos,
    onPhotos,
}: {
    id: string;
    photos: File[];
    onPhotos: (files: File[]) => void;
}) {
    return (
        <div className="grid gap-2">
            <FileDropZone
                icon={Camera}
                accept="image/jpeg,image/png"
                multiple
                title="Agregar foto"
                hint="De su cámara o su galería, en JPG o PNG"
                testId={`photo-${id}`}
                onFiles={(files) => onPhotos([...photos, ...files])}
            />
            <ul className="grid gap-1">
                {photos.map((photo, index) => (
                    <li
                        key={`${photo.name}-${index}`}
                        className="flex items-center justify-between gap-2 text-base"
                    >
                        <span className="min-w-0 truncate">{photo.name}</span>
                        <button
                            type="button"
                            aria-label={`Quitar ${photo.name}`}
                            className="flex size-9 shrink-0 items-center justify-center rounded-md hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                            onClick={() =>
                                onPhotos(photos.filter((_, at) => at !== index))
                            }
                        >
                            <X aria-hidden className="size-4" />
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
