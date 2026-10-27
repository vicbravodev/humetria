Pregunta de la encuesta con la escala oficial como lista vertical de opciones grandes.

**Recibe:** `number`, `total`, `text` (enunciado oficial, sin cambios), `options` (por defecto Siempre / Casi siempre / Algunas veces / Casi nunca / Nunca) y `defaultValue`.

- Cada opción es una fila completa de 56 px: el círculo de 24 px, el texto en `body-lg` y toda la fila clicable.
- La opción elegida lleva borde `primary` de 2 px, fondo `accent` y texto en 500: tres señales, no solo color.
- Elegir una opción NUNCA avanza solo; la persona confirma con “Siguiente”.
- Es un `fieldset` con `legend`, navegable con flechas y tabulador.
