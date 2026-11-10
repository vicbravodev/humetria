import Notice from '@/components/humetria/notice';

/**
 * Some groups of the cut are not shown. It never says how many groups or people: the difference with the total would
 * point at them.
 */
export default function HiddenGroupsNotice() {
    return (
        <Notice>
            <strong>Algunos grupos no se muestran.</strong> Son demasiado
            pequeños para proteger el anonimato de quien contestó: el sistema no
            guarda quién contestó qué y solo muestra resultados de grupos de al
            menos 5 respuestas.
        </Notice>
    );
}
