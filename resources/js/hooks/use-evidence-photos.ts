import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import PortalEvidenceController from '@/actions/App/Modules/Telework/Http/Controllers/PortalEvidenceController';
import { GENERAL, photoFields } from '@/lib/telework/answers';

/** The photos a person picks for one request of the Comisión, and their single send. */
export function useEvidencePhotos(token: string, requestId: string) {
    const [photos, setPhotos] = useState<File[]>([]);
    const form = useForm({});
    const errors: Partial<Record<string, string>> = form.errors;

    return {
        photos,
        setPhotos,
        error: errors['photos'] ?? errors['evidence'],
        processing: form.processing,
        send: () => {
            form.transform(() => photoFields({ [GENERAL]: photos }));
            form.post(
                PortalEvidenceController.photos.url({
                    token,
                    evidenceRequest: requestId,
                }),
                { forceFormData: true },
            );
        },
    };
}
