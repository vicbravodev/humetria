import { useState } from 'react';

/** Copies a message to the clipboard so the person can paste it where they ask for help; says so once it is copied. */
export function useCopyMessage() {
    const [copied, setCopied] = useState(false);

    return {
        copied,
        copy: (text: string) => {
            void navigator.clipboard
                .writeText(text)
                .then(() => setCopied(true));
        },
    };
}
