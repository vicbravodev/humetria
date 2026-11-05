import { router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { useRef, useState } from 'react';

/**
 * A PUT that keeps what the person answered when it does not reach the server (V3-Estados, «No se pudo guardar»): it says
 * so and lets them try again with the same answers. Validation errors come back as usual, in `errors`.
 */
export function useSave() {
    const [processing, setProcessing] = useState(false);
    const [failed, setFailed] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const last = useRef<(() => void) | null>(null);

    const save = (
        url: string,
        data: RequestPayload,
        onSuccess: () => void = () => {},
    ) => {
        last.current = () => {
            router.put(url, data, {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setFailed(false);
                },
                onSuccess: () => {
                    setErrors({});
                    onSuccess();
                },
                onError: setErrors,
                onNetworkError: () => {
                    setFailed(true);

                    return false;
                },
                onHttpException: () => {
                    setFailed(true);

                    return false;
                },
                onFinish: () => setProcessing(false),
            });
        };
        last.current();
    };

    return { save, retry: () => last.current?.(), processing, failed, errors };
}
