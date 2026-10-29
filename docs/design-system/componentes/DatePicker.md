Campo de fecha que abre un calendario en español; reemplaza al `<input type="date">` del navegador, que se ve distinto en cada sistema y no respeta el tema.

**Recibe:** `id`, `name`, `defaultValue` y `min` (fechas `Y-m-d`, como las manda el servidor), `invalid` y `describedBy`.

- Se ve como `Input`: 44 px, borde `input`, ícono de calendario y la fecha escrita completa (“30 de noviembre de 2026”). Sin fecha, “Elige una fecha” en `muted-foreground`.
- Al tocarlo abre un `Popover` con el `Calendar`: semanas desde el lunes, días de 36 px, hoy subrayado en `primary`, el elegido con fondo `primary`. Los días antes de `min` quedan atenuados y no se pueden elegir, y no deja retroceder a meses sin días permitidos.
- El foco entra al día que el teclado puede elegir (el elegido, hoy o el primero permitido); las flechas recorren los días. Elegir uno cierra el calendario y devuelve el foco al campo.
- Al cambiar de mes las semanas entran de lado (200 ms) y las anteriores salen hacia el otro (150 ms); con movimiento reducido solo se desvanecen.
- El formulario recibe la fecha en un campo oculto como `Y-m-d`; el navegador no hace cuentas de zona horaria.
