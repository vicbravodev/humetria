Ventana modal para confirmar decisiones que no se pueden deshacer; equivale a `Dialog`/`AlertDialog` de shadcn.

**Recibe:** `title` (pregunta concreta), `description` (consecuencia), `footer` (acciones) e `inline` solo para la vista previa.

- El título pregunta lo que va a pasar (“¿Cerrar la campaña Planta Apodaca?”) y la descripción dice la consecuencia con datos.
- La acción segura va primero y en `outline`; la destructiva, a la derecha.
- Fondo `popover`, radio `radius-xl`, `shadow-float`, velo oscuro al 48 %.
