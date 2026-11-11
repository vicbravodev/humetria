import { useEffect, useState } from 'react';
import { cancelRender, continueRender, delayRender } from 'remotion';

/**
 * Revisión de desbordes (solo con `probe: true`). Mide el DOM ya pintado y avisa por consola con el prefijo [layout]:
 * cualquier caja cuyo contenido se salga de ella (texto largo, botones que no caben, hijos fuera de su tarjeta) y cualquier
 * elemento fuera de los márgenes del lienzo. Va antes de renderizar: un desborde se encuentra aquí, no en un render de 3 minutos.
 * `scripts/qa-layout.mjs` lo corre sobre fotogramas ya asentados de cada capítulo.
 */
export const useLayoutProbe = (active: boolean): void => {
    // El retraso se pide al pintar (no en el efecto) para que el fotograma espere la medición.
    const [handle] = useState(() => (active ? delayRender('layout probe') : null));
    useEffect(() => {
        if (!active || handle === null) {
            return;
        }
        const raf = requestAnimationFrame(() => {
            const scope = document.querySelector('[data-probe-scope]');
            const issues = new Set<string>();
            const name = (el: Element): string => `${el.tagName.toLowerCase()} «${(el.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 34)}»`;
            scope?.querySelectorAll<HTMLElement>('*').forEach((el) => {
                if (el.closest('[data-probe-ignore]')) {
                    return;
                }
                const style = getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) < 0.15) {
                    return;
                }
                const r = el.getBoundingClientRect();
                if (r.width < 2 || r.height < 2) {
                    return;
                }
                const big = el.clientWidth >= 1800 && el.clientHeight >= 1000;
                if (!big && el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 4 && style.overflowX !== 'auto') {
                    issues.add(`ANCHO: ${name(el)} contiene ${el.scrollWidth - el.clientWidth}px más que su caja`);
                }
                // Un texto de una línea con line-height ajustado excede su caja unos px por la métrica de la tipografía: no es desborde.
                const fonts = [el, ...Array.from(el.children)].map((n) => parseFloat(getComputedStyle(n).fontSize) || 0);
                const slack = Math.max(el.children.length === 0 ? 9 : 4, Math.max(...fonts) * 0.16);
                if (!big && el.clientHeight > 0 && el.scrollHeight > el.clientHeight + slack && style.overflowY !== 'auto') {
                    issues.add(`ALTO: ${name(el)} contiene ${el.scrollHeight - el.clientHeight}px más que su caja`);
                }
                const text = (el.textContent ?? '').trim().length > 0 && el.children.length === 0;
                if (text && (r.left < 40 || r.right > 1880 || r.top < 20 || r.bottom > 1060)) {
                    issues.add(`LIENZO: ${name(el)} queda fuera de los márgenes (${Math.round(r.left)},${Math.round(r.top)})-(${Math.round(r.right)},${Math.round(r.bottom)})`);
                }
            });
            if (issues.size > 0) {
                // Un error de render sí llega a la terminal (la consola del navegador no).
                cancelRender(new Error(`[layout] ${issues.size} problema(s):\n${[...issues].map((i) => `  ${i}`).join('\n')}`));
                return;
            }
            continueRender(handle);
        });
        return () => cancelAnimationFrame(raf);
    });
};
