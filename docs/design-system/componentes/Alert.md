Mensaje en línea que la persona debe leer sin interrumpir su tarea (`components/ui/alert.tsx`, sobre `Alert` de shadcn). Para algo de la página que hay que revisar, con su acción, usa `Notice`.

**Recibe:** `variant` (`default`, `destructive`) y, dentro, un ícono opcional de `lucide-react`, `AlertTitle` y `AlertDescription`.

- `default`: fondo `background` con borde. Con el candado en `primary`, es el aviso de confidencialidad.
- `destructive` lleva borde, ícono y título en `destructive`; el texto de cuerpo se queda en `foreground` para no saturar.
- Nada de bordes laterales de color: el ícono y el título bastan.
