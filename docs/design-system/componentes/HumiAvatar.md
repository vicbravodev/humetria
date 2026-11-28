La cara de Humi en un círculo `accent` (`components/humetria/humi-avatar.tsx`, sobre `Humi` de `humi.tsx`, con
`humi-eyes.tsx` y `humi-crown.tsx`), y el foco de los recorridos guiados (`components/humetria/spotlight-tour.css`).

**Recibe:** `expression` (`hola` por omisión), `size` en px (36 por omisión), `animated` (flota y parpadea; se detiene
solo con movimiento reducido) y `className` (otro tinte del círculo: `bg-card` donde se sienta sobre `accent`).

- **Dónde:** el encabezado, las respuestas y la burbuja de Humi; Primeros pasos; el dock del guía de la demo (40 px en la
  cabecera, 32 px en la píldora y en la línea que celebra un paso).
- **Colores:** los del tema (`primary`, `--humi-eye`, `--humi-pupil`, `--humi-mouth`), iguales en claro y oscuro.
- **Foco de un recorrido:** la burbuja de driver.js lleva la clase `first-steps-tour` y se viste con los tokens
  (`popover`, `border`, `shadow-float`, botones como `Button`).
  El guía de la demo no usa la burbuja ni el velo: marca el control de su paso con `data-guide-spotlight`, un contorno
  `primary` de 3 px que no tapa la página ni le quita los clics al dock.
