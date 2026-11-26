Tabla de datos para listas comparables (áreas, campañas cerradas, centros archivados, personal); equivale a `Table` de
shadcn.

**Tabla o tarjeta.** La tabla es para **muchas entidades parecidas que se buscan y se comparan**; la tarjeta, para pocas
con su estado y su acción propia (ver `Card.md`). Lo terminado o archivado va en tabla aunque lo vivo de la misma
pantalla vaya en tarjetas.

**Recibe:** `columns` (`{key, label, align, render}`), `rows` y `caption` (para lectores de pantalla).

- Encabezado en estilo `caption` sobre `muted`; filas de 52 px con divisor `border`.
- Números alineados a la derecha con cifras tabulares.
- Una segunda línea en `muted-foreground` sustituye columnas extra (“Supervisa: L. Garza”).
- Las acciones de una fila van en un solo botón “…” (`RowMenu`, `components/humetria/row-menu.tsx`, con `label` «Acciones de {nombre}») al final, con un menú; la que destruye va al último, separada y en `destructive`. Así la tabla cabe en escritorio sin desplazarse de lado. Lo que solo aplica a una celda (reenviar o cancelar una invitación) se queda en la celda, en botones `sm`.
- En móvil, la tabla se desplaza en horizontal dentro de su marco; no la conviertas en tarjetas a mano. Si en el celular cada fila debe leerse completa (cintas, avisos y su acción), usa `StackedTable` (`components/humetria/stacked-table.tsx`), que hace la tarjeta solo en el celular y sigue siendo tabla en papel. El marco recibe el foco (con el anillo `ring`) para que también el teclado pueda desplazarla.
