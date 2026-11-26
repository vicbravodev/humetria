Pestañas para alternar vistas del mismo contexto. En la app no hay un `Tabs` de shadcn: se usan estas dos piezas.

- **`CountTabs`** (`components/humetria/count-tabs.tsx`): las vistas de una lista con su conteo («Pendientes 42 ·
  Contestaron 18 · Todas 60»). Cada pestaña es un enlace: la vista vive en la URL y el servidor pagina. Es la que se usa
  casi siempre.
- **`SectionTabs`** (`components/humetria/section-tabs.tsx`): las partes de un reporte largo, fijas arriba mientras se
  lee; la parte en pantalla va subrayada y las acciones a la derecha.

- La pestaña activa es una pastilla `card` sobre la pista `muted` (38 px de alto, 3 px de relleno), sin color de acento.
- Muestra el conteo cuando ayuda a decidir (“Activas 3”).
- Máximo 4 pestañas; si son más, es navegación y va en el `Sidebar`.
