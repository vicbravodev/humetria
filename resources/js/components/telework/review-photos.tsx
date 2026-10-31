import { show } from '@/routes/tenant/telework/photos';
import { show as showEvidence } from '@/routes/tenant/telework/evidence-photos';
import type { ReviewPhoto } from '@/types';

/** Thumbnails of the photos a person sent; each opens the full picture, served only to who may see it. */
export default function ReviewPhotos({
    tenantSlug,
    photos,
    evidence = false,
}: {
    tenantSlug: string;
    photos: ReviewPhoto[];
    /** Photos sent for a request of the Comisión, not those of the checklist. */
    evidence?: boolean;
}) {
    if (photos.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {photos.map((photo, index) => {
                const href = (evidence ? showEvidence : show)({
                    tenant: tenantSlug,
                    photo: photo.id,
                }).url;

                return (
                    <li key={photo.id}>
                        <a href={href} target="_blank" rel="noreferrer">
                            <img
                                src={href}
                                alt={`Foto ${index + 1}`}
                                loading="lazy"
                                className="size-24 rounded-lg border object-cover"
                            />
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}
