Etiqueta corta de estado o categoría (`components/ui/badge.tsx`, sobre `Badge` de shadcn).

**Recibe:** `variant` (`default`, `secondary`, `success`, `warning`, `destructive`, `outline`) y el texto.

- Píldora de 26 px (`rounded-full`), 13 px en 500, con relleno suave y un punto del color del texto antes de él. Nunca un bloque sólido de color.
- `default`: `primary` sobre `accent` (en curso, activo). `secondary`: `muted-foreground` sobre `secondary` (borrador, cerrado, sin empezar). `success`: tonos de `risk-bajo` (listo, confirmó, activada). `warning`: `warning-foreground` sobre `warning` (por aprobar, vence pronto, Consola). `destructive`: tonos de `risk-alto` (rechazado, vencido). `outline`: `card` con borde y punto `muted-foreground` (categorías neutras).
- El texto siempre dice el estado; el color nunca va solo.
- Para niveles de riesgo usa `RiskLevel`, no Badge.
