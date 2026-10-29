Etiqueta corta de estado o categoría; equivale a `Badge` de shadcn más tres variantes de estado de campaña.

**Recibe:** `variant` (`default`, `secondary`, `outline`, `destructive`, `active`, `draft`, `closed`) y el texto.

- Estados de campaña: `active` “En curso” (con punto jade), `draft` “Borrador”, `secondary` “Lista para reporte”, `closed` “Cerrada”.
- Relleno suave y un punto de color antes del texto, a 13 px en 500: `default` sobre `accent` con punto `primary`, `secondary` con punto `muted-foreground`, `destructive` con borde y texto `destructive` sobre `card`. Nunca un bloque sólido de color.
- El texto siempre dice el estado; el color nunca va solo.
- Para niveles de riesgo usa `RiskLevel`, no Badge.
