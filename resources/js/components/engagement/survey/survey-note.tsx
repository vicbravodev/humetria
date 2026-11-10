import { focusOnMount } from '@/lib/focus';

/** A card that says something before going on: a section's instruction or the last word before sending. It takes the focus. */
export default function SurveyNote({
    title,
    children,
}: {
    title: string;
    children: string;
}) {
    return (
        <div
            ref={focusOnMount}
            tabIndex={-1}
            className="flex flex-col gap-2.5 rounded-xl bg-accent px-4 py-3.5 text-[17px] leading-[26px] text-accent-foreground focus:outline-none"
        >
            <p className="font-semibold">{title}</p>
            <p>{children}</p>
        </div>
    );
}
