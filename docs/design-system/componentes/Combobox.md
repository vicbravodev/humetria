Lista desplegable con búsqueda (`components/humetria/combobox.tsx`): para más de 7 opciones o para opciones que viven en el servidor. Se ve como `Select`; al abrirlo, un campo «Buscar» filtra las opciones debajo.

**Recibe:** `id`, `name`, `source`, `defaultValue` (`{value, label}`), `placeholder`, `invalid` y `describedBy`.

- Mismo alto, borde y foco que `Select`; la flecha gira media vuelta al abrir y el menú usa `popover` y `shadow-float`, del ancho del campo.
- La búsqueda ignora mayúsculas y acentos («queretaro» encuentra «Querétaro»). Primero van las opciones que empiezan con lo escrito, luego las que tienen una palabra que empieza así y al final las que solo lo contienen.
- Teclado: las flechas, Inicio y Fin mueven la opción activa (siempre a la vista) y Enter la elige. Es un `combobox` con su `listbox` y `aria-activedescendant`, como `CommandPalette`.
- Opciones de 40 px; la activa con fondo `accent`; la elegida en 500 con palomita en `primary`. Elegir una cierra el menú y regresa el foco al campo.
- Debajo de las opciones, una línea en `muted-foreground` dice qué pasa: «Sin resultados», «Buscando…», «Escribe para encontrar más.» o «No se pudo buscar. Intenta de nuevo.».
- `source: { options }`: todas las opciones vienen con la página y se filtran en el navegador al instante, con las etiquetas ya normalizadas, así Enter siempre elige lo que está en pantalla. Se muestran como máximo 50: con más, se escribe para acotar.
- `source: { initial, load, minLength, delay }`: las opciones viven en el servidor. Se muestran las pocas de `initial` hasta que se escriben `minLength` letras (2 por omisión). Luego se pregunta al servidor cuando la escritura se detiene `delay` ms (250 por omisión), una sola vez por el último texto; la petición que queda vieja se cancela (`AbortSignal`) y su respuesta se ignora. Mientras carga se quedan los resultados anteriores, y un texto ya preguntado se responde de memoria. El servidor devuelve pocas opciones, nunca la tabla entera.
- El formulario recibe el `value` elegido en un campo oculto con `name`.
