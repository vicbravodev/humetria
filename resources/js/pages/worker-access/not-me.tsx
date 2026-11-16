import { Head } from '@inertiajs/react';
import NotMeConfirm from '@/components/worker-access/not-me-confirm';
import NotMeDone from '@/components/worker-access/not-me-done';

/** «No fui yo», from the email of a new key: confirm, then done. */
export default function NotMe({
    company,
    label,
    confirmUrl,
}: {
    company: string;
    label: string;
    /** Null once the key is no longer usable. */
    confirmUrl: string | null;
}) {
    return (
        <>
            <Head title="No fui yo" />
            {confirmUrl === null ? (
                <NotMeDone company={company} />
            ) : (
                <NotMeConfirm
                    company={company}
                    label={label}
                    confirmUrl={confirmUrl}
                />
            )}
        </>
    );
}
