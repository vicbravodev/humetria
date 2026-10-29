Ventana modal para confirmar decisiones que no se pueden deshacer; equivale a `Dialog`/`AlertDialog` de shadcn.

**Recibe:** `title` (pregunta concreta), `description` (consecuencia), `footer` (acciones) e `inline` solo para la vista previa.

- El título pregunta lo que va a pasar (“¿Cerrar la campaña Planta Apodaca?”) y la descripción dice la consecuencia con datos.
- La acción segura va primero y en `outline`; la destructiva, a la derecha.
- Fondo `popover`, radio `radius-xl`, `shadow-float`, velo oscuro al 48 %. Título en 20/28 y 600.
- Las acciones van en una franja a todo lo ancho (`DialogFooter`), sobre `muted` y bajo un divisor.
- Entra con un desvanecido y desde `scale(0.96)` en 200 ms; sale en 150 ms. Se abre centrado, no desde su botón.
