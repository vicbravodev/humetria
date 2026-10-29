Tabla de datos para listas comparables (áreas, campañas cerradas, personal); equivale a `Table` de shadcn.

**Recibe:** `columns` (`{key, label, align, render}`), `rows` y `caption` (para lectores de pantalla).

- Encabezado en estilo `caption` sobre `muted`; filas de 52 px con divisor `border`.
- Números alineados a la derecha con cifras tabulares.
- Una segunda línea en `muted-foreground` sustituye columnas extra (“Supervisa: L. Garza”).
- Las acciones de una fila van en un solo botón “…” (`Acciones de {nombre}`) al final, con un menú; la que destruye va al último, separada y en `destructive`. Así la tabla cabe en escritorio sin desplazarse de lado. Lo que solo aplica a una celda (reenviar o cancelar una invitación) se queda en la celda, en botones `sm`.
- En móvil, la tabla se desplaza en horizontal dentro de su marco; no la conviertas en tarjetas.
