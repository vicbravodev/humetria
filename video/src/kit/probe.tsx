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
                // Aire mínimo: el contenido de una caja pintada (tarjeta, fila, botón con texto) no puede quedar a menos de 8 px de su borde.
                const bs = style;
                const paintedBox = (bs.backgroundColor !== 'rgba(0, 0, 0, 0)' && bs.backgroundColor !== 'transparent') || parseFloat(bs.borderTopWidth) > 0;
                if (!big && paintedBox && el.children.length > 0 && (el.textContent ?? '').trim().length > 0 && el.clientWidth < 1800) {
                    let top = Infinity;
                    let left = Infinity;
                    let bottom = -Infinity;
                    let right = -Infinity;
                    el.querySelectorAll<HTMLElement>('*').forEach((d) => {
                        if (d.closest('[data-probe-ignore]')) {
                            return;
                        }
                        const ds = getComputedStyle(d);
                        const dr = d.getBoundingClientRect();
                        if (ds.display === 'none' || ds.visibility === 'hidden' || parseFloat(ds.opacity) < 0.15 || dr.width < 2 || dr.height < 2) {
                            return;
                        }
                        top = Math.min(top, dr.top);
                        left = Math.min(left, dr.left);
                        bottom = Math.max(bottom, dr.bottom);
                        right = Math.max(right, dr.right);
                    });
                    if (bottom > -Infinity) {
                        const gaps: [string, number][] = [['superior', top - r.top], ['izquierdo', left - r.left], ['inferior', r.bottom - bottom], ['derecho', r.right - right]];
                        const tight = gaps.filter(([, g]) => g < 8);
                        if (tight.length > 0) {
                            issues.add(`PEGADO: contenido de ${name(el)} sin aire en el borde ${tight.map(([n, g]) => `${n} (${Math.round(g)}px)`).join(', ')}`);
                        }
                    }
                }
                if (!big && el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 4 && style.overflowX !== 'auto') {
                    issues.add(`ANCHO: ${name(el)} contiene ${el.scrollWidth - el.clientWidth}px más que su caja`);
                }
                // Un texto de una línea con line-height ajustado excede su caja unos px por la métrica de la tipografía: no es desborde.
                const fonts = [el, ...Array.from(el.children)].map((n) => parseFloat(getComputedStyle(n).fontSize) || 0);
                // Tolerancia: solo los textos grandes (≥ 60 px, con line-height 1) excedan su caja por la métrica de la tipografía.
                const biggest = Math.max(...fonts);
                const slack = biggest >= 60 ? biggest * 0.16 : el.children.length === 0 ? 9 : 4;
                if (!big && el.clientHeight > 0 && el.scrollHeight > el.clientHeight + slack && style.overflowY !== 'auto') {
                    issues.add(`ALTO: ${name(el)} contiene ${el.scrollHeight - el.clientHeight}px más que su caja`);
                }
                const text = (el.textContent ?? '').trim().length > 0 && el.children.length === 0;
                if (text) {
                    // Texto pegado al borde de la caja visible que lo contiene (fondo o contorno): menos de 8 px de aire.
                    const range = document.createRange();
                    range.selectNodeContents(el);
                    const tr = range.getBoundingClientRect();
                    let box: HTMLElement | null = el.parentElement;
                    for (let i = 0; i < 4 && box; i++, box = box.parentElement) {
                        const bs = getComputedStyle(box);
                        const painted = (bs.backgroundColor !== 'rgba(0, 0, 0, 0)' && bs.backgroundColor !== 'transparent') || parseFloat(bs.borderTopWidth) > 0;
                        if (painted && box.clientWidth < 1800) {
                            const br = box.getBoundingClientRect();
                            const gaps = [tr.left - br.left, br.right - tr.right];
                            if (Math.min(...gaps) < 8 && tr.width > 0) {
                                issues.add(`PEGADO: ${name(el)} a ${Math.round(Math.min(...gaps))}px del borde de su caja`);
                            }
                            break;
                        }
                    }
                }
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
