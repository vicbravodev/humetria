Los cuestionarios de un grupo por nivel de riesgo, en una sola barra: cada nivel ocupa el ancho de su parte, siempre de Nulo (izquierda) a Muy alto (derecha), con un hueco de 2 px del fondo entre segmentos.

**Recibe:** `counts` (los cinco conteos en el orden de los niveles), `levels` (el catálogo), `label` (para `aria-label`) y `className` (la altura: 20 px en filas, 32 px como barra principal).

- El nivel lo dicen la posición y la leyenda (`LevelLegend`) junto a la barra; el color solo confirma. Al pasar el cursor, un tooltip da el conteo y el porcentaje de cada segmento.
- Rellenos `risk-*` (≥ 3:1 sobre `card`); en impresión conserva el color (`print-color-adjust: exact`).
- No lleva texto dentro de los segmentos: los números van en la leyenda, en la columna de al lado o en la tabla de conteos que la acompaña.
- Los niveles vecinos de terracota se parecen en oscuro; por eso la barra nunca va sola: siempre con leyenda, orden fijo y la tabla a un clic.
