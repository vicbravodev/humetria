Tarjeta de una campaña o centro de trabajo en el panel de RH: avance contra la muestra, estado y siguiente acción.

**Recibe:** `name`, `meta` (guía y fecha), `status` (`active`, `draft`, `ready`, `closed`), `responses`, `sample`, `risk` (opcional, al cerrar), `nextTitle`, `nextNote`, `nextAction`, `nextIcon`, `nextVariant`.

- Siempre responde tres preguntas: ¿cuánto llevo?, ¿en qué estado está? y ¿qué hago ahora?
- La siguiente acción es una sola y concreta; usa `default` solo cuando ya hay un entregable (“Ver reporte”).
- Las campañas cerradas no usan tarjeta: van en una `Table` debajo, separadas por `space-12`.
