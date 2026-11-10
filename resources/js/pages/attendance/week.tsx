import { Head } from '@inertiajs/react';
import WeekScreen from '@/components/attendance/week-screen';
import { useWeek } from '@/hooks/use-week';

/**
 * «Mi semana» (criterios 5.2, 5.4 and 8.1; board P4): after their device verifies them, a person sees their marks and hours
 * of one week, why RH corrected a mark, and confirms it or says what is not right; they ask RH to correct a mark or to add
 * one they forgot, and see where each request stands.
 */
export default function Week({
    company,
    start,
    optionsUrl,
    weekUrl,
    punchUrl,
    correctionOptionsUrl,
    correctionUrl,
}: {
    company: string;
    start: string | null;
    optionsUrl: string;
    weekUrl: string;
    punchUrl: string;
    correctionOptionsUrl: string;
    correctionUrl: string;
}) {
    const state = useWeek({
        optionsUrl,
        weekUrl,
        start,
        correctionOptionsUrl,
        correctionUrl,
    });

    return (
        <>
            <Head title="Mi semana" />
            <WeekScreen company={company} punchUrl={punchUrl} state={state} />
        </>
    );
}
