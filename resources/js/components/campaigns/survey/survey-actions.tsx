import type { ReactNode } from 'react';

/**
 * The buttons of a survey screen, stuck to the bottom of a phone where the thumb is (as `PersonFrame` does); on a computer
 * they follow the content. Goes last in a screen that is a column filling the height.
 */
export default function SurveyActions({ children }: { children: ReactNode }) {
    return (
        <div className="sticky bottom-0 z-10 -mx-4 mt-auto border-t bg-background px-4 pt-3 pb-4 md:static md:mx-0 md:border-t-0 md:bg-transparent md:px-0 md:pt-2 md:pb-0">
            <div className="flex w-full flex-col gap-1.5">{children}</div>
        </div>
    );
}
