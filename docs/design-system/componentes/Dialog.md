Ventana modal centrada; equivale a `Dialog`/`AlertDialog` de shadcn (`ConfirmDialog` y `FormDialog` en
`components/humetria/`).

**Cuándo sí:** confirmar una decisión (sobre todo la que no se puede deshacer), destruir algo, o una acción corta de uno
o dos campos sobre algo que ya existe (rechazar, cerrar un caso, compartir, publicar, extender una fecha, registrar un
pago), y un formulario que necesita el ancho de un mapa (la zona de asistencia). La consola y los cobros siguen la misma
regla que el resto (ver «Formularios» en `design-system.md`).
**Cuándo no:** dar de alta o editar un registro con varios campos con una lista detrás va en el panel lateral
(`Sheet.md`).

**Recibe:** `title` (pregunta concreta), `description` (consecuencia), `footer` (acciones) e `inline` solo para la vista previa.

- El título pregunta lo que va a pasar (“¿Cerrar la campaña Planta Apodaca?”) y la descripción dice la consecuencia con datos.
- La acción segura va primero y en `outline`; la destructiva, a la derecha.
- Fondo `popover`, radio `radius-xl`, `shadow-float`, velo oscuro al 48 %. Título en 20/28 y 600.
- Las acciones van en una franja a todo lo ancho (`DialogFooter`), sobre `muted` y bajo un divisor.
- Entra con un desvanecido y desde `scale(0.96)` en 200 ms; sale en 150 ms. Se abre centrado, no desde su botón.
