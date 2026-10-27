Botón de acción; mapea 1:1 al `Button` de shadcn/ui con las variantes `default`, `secondary`, `outline`, `ghost`, `destructive` y `link`.

**Recibe:** `variant`, `size` (`default` 44 px · `sm` 36 px visibles con área táctil de 44 px · `lg` 52 px · `icon` 44×44), `icon` / `iconRight` (nombre de ícono), `block` para ancho completo, `children` (texto en verbo + objeto).

- Un solo `default` (primario) por vista o tarjeta. Las demás acciones van en `outline` o `ghost`.
- `destructive` solo para eliminar o cerrar algo que no se puede deshacer, y siempre detrás de un `Dialog`. Su texto usa `on-destructive` (blanco en claro, `#0F1716` en oscuro): en shadcn cambia `text-white` por `text-on-destructive` en la variante.
- `outline` usa el borde `input` (≥ 3:1), no `border`.
- Botón de solo ícono: siempre con `aria-label`.
- Nada de sombras ni degradados; el hover oscurece el fondo un 12 %.
