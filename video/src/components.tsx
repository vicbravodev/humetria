
export const icons = {
    file: (
        <>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
            <path d="M14 3v5h5" />
        </>
    ),
    upload: (
        <>
            <path d="M12 16V4" />
            <path d="m6 10 6-6 6 6" />
            <path d="M5 20h14" />
        </>
    ),
    mail: (
        <>
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
        </>
    ),
    bell: (
        <>
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </>
    ),
    lock: (
        <>
            <rect x="4" y="11" width="16" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
        </>
    ),
    check: <path d="M5 12.5l4.5 4.5L19 7.5" />,
    arrowUp: (
        <>
            <path d="M12 19V5" />
            <path d="m5 12 7-7 7 7" />
        </>
    ),
    arrowRight: (
        <>
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
        </>
    ),
    listCheck: (
        <>
            <path d="M11 6h10" />
            <path d="M11 12h10" />
            <path d="M11 18h10" />
            <path d="m3 6 1.5 1.5L7 5" />
            <path d="m3 12 1.5 1.5L7 11" />
            <path d="m3 18 1.5 1.5L7 17" />
        </>
    ),
    eyeOff: (
        <>
            <path d="M3 3l18 18" />
            <path d="M10.6 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a17 17 0 0 1-3.2 4.2" />
            <path d="M6.6 6.6A17 17 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        </>
    ),
    shield: (
        <>
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
            <path d="m9 12 2 2 4-4" />
        </>
    ),
} as const;
