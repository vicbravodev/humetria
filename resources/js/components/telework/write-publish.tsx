import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { publishCopy } from '@/lib/telework/writing';
import type { WritingPublication } from '@/types';

/**
 * Publishing a written NOM-037 document from its review (board V2-Politica): who it reaches, the version in force and the
 * button, with what stopped it when it did.
 */
export default function WritePublish({
    policy,
    publication,
    url,
}: {
    policy: boolean;
    publication: WritingPublication;
    url: string;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const copy = publishCopy(policy, publication);
    const send = () =>
        router.post(
            url,
            {},
            {
                onStart: () => setProcessing(true),
                onError: (errors) =>
                    setError(
                        errors['answers'] ?? errors['work_center_ids'] ?? null,
                    ),
                onFinish: () => setProcessing(false),
            },
        );

    return (
        <div className="flex flex-col gap-4">
            <p className="text-[17px] leading-[26px] text-muted-foreground">
                {policy ? 'Léela' : 'Léelo'} a la derecha. {copy.text}
            </p>
            {publication.version !== null && (
                <p className="text-sm text-muted-foreground">
                    Vigente: versión {publication.version.number} del{' '}
                    {publication.version.publishedOn}
                </p>
            )}
            <div>
                <Button size="lg" disabled={processing} onClick={send}>
                    {processing && <Spinner />}
                    {copy.action}
                </Button>
            </div>
            {error !== null && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
