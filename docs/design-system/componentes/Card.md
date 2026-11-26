Contenedor con borde para agrupar información relacionada; equivale a `Card` de shadcn.

**Recibe:** `title`, `description`, `children` (contenido) y `footer` (acciones).

- Fondo `card`, borde `border`, radio `radius-xl` (14 px), sin sombra: `Card` de `components/ui/card.tsx` o la utilidad `card` de `resources/css/app.css` cuando el contenedor es una sección, un elemento de lista o un enlace.
- Padding `space-6`; separa tarjetas con `space-4` o `space-6`.
- No anides tarjetas dentro de tarjetas.

**Tarjeta o tabla.** La tarjeta es para **pocas entidades, cada una con su estado y su acción propia**: una campaña viva,
un centro de trabajo activo. Cada una responde qué tan avanzada va, en qué estado está y qué sigue. Cuando las entidades
son **muchas y se comparan** (personal, campañas cerradas, centros archivados) van en `Table`. Lo vivo va en tarjetas y
lo terminado en tabla, aparte. La consistencia que se busca es de regla, no de apariencia: dos pantallas pueden verse
distinto si sus listas son de distinto tipo.

- Una lista en tarjetas también se pagina y se busca en el servidor.
- Dentro de una fila de tarjetas, las columnas dependen de cuántas tarjetas hay: si una tarjeta opcional falta, la otra
  ocupa todo el ancho y pone su contenido en una sola fila en lugar de dejar un hueco.
- El contenido de una tarjeta va en orden vertical: encabezado (título y frase) arriba, el contenido a todo lo ancho y
  la acción al pie. No dejes una columna lateral solo para el título.
