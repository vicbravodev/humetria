Panel lateral derecho para dar de alta o editar un registro sin perder de vista la lista; es `FormSheet`
(`components/humetria/form-sheet.tsx`), sobre `Sheet` de shadcn.

**Cuándo:** alta o edición de un registro con varios campos, y hay una lista detrás → panel lateral derecho.
Confirmar, destruir o una acción de uno o dos campos → modal centrado (`Dialog.md`).

**Recibe:** `title`, `description`, `form` (el `.form()` de Wayfinder), `submit` (el verbo de la acción),
`trigger` opcional (un `SheetTrigger`) y los campos como `children(errors)`.

- Un solo panel: fondo `popover`, a todo lo ancho en el celular (`w-full`) y de 520 px desde la tableta, separado
  8 px del borde, con radio `radius-xl` y `shadow-float`. No se copia su clase en otro archivo: se usa `FormSheet`.
- Encabezado con título en 18/26 y descripción bajo un divisor; los campos se desplazan dentro del panel; el pie
  (Cancelar en `outline` y la acción) queda fijo abajo, bajo un divisor.
- Se cierra cuando el servidor acepta el formulario y la página conserva su scroll; con errores se queda abierto.
- El foco entra al panel al abrirlo, `Escape` lo cierra y el foco regresa al botón que lo abrió.
- Lo usan: empleados, compensación, acciones e hileras del programa de intervención, centros de trabajo, días
  festivos y tipos de permiso, compromisos y pulso de clima, y los registros de teletrabajo. Una prueba
  (`tests/Unit/FormSheetArchitectureTest.php`) impide que esos formularios regresen a un diálogo.
- **Se quedan como modal:** la consola de superadmin y los cobros (empresa, dueño, cuenta, suscripción, cargo: otra
  audiencia, sin lista del panel detrás), la zona de asistencia (su mapa necesita el ancho del modal), todo
  `ConfirmDialog` y las acciones cortas sobre algo que ya existe (rechazar, cerrar, compartir, publicar, extender,
  registrar un pago, ajustar un saldo…).
