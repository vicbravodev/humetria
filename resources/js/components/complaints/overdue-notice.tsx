import Notice from '@/components/humetria/notice';
import { countOf } from '@/lib/plural';

/** Acknowledgements past the days the company set itself: only how many. Who has the cases is the handlers' to see. */
export default function OverdueNotice({ count }: { count: number }) {
    if (count === 0) {
        return null;
    }

    return (
        <Notice>
            <strong>{countOf(count, 'caso', 'casos')} sin acusar recibo</strong>{' '}
            pasado el plazo que fijó la empresa. Quienes los atienden lo ven
            marcado; tú solo ves cuántos son.
        </Notice>
    );
}
