Navegación lateral del panel de RH; usa el `Sidebar` de shadcn con los tokens `sidebar-*`.

**Recibe:** `current` (elemento activo), `items` (opcional: `{icon, label, count}`), `user`, `org`, `initials`.

- El logo de Humetría va arriba; en white label lo reemplaza el logo del consultor y Humetría no aparece.
- Elemento activo: fondo `sidebar-accent`, texto en 600 e ícono en `sidebar-primary`.
- Un solo botón primario (“Nueva campaña”) y máximo 7 elementos.
- En escritorio es el marco de la app (`variant="inset"`): la página va en un panel con radio `radius-xl`, borde `sidebar-border` y `shadow-raised`.
- Se pliega a sus íconos (`collapsible="icon"`) con el botón de la barra superior del panel o con ⌘B (Ctrl+B); recuerda el estado en la cookie `sidebar_state`. Plegado, el logo pasa al símbolo, cada elemento conserva su área de 44 × 44 px y su nombre aparece como tooltip.
- La barra superior del panel se queda arriba al desplazar la página y muestra un divisor cuando el contenido pasa por debajo. A la derecha lleva el selector de tema (Claro, Oscuro, Sistema): un grupo de radios con un chip elevado que se desliza al elegido.
- En móvil se pliega en un `Sheet` desde la izquierda.
- Etiqueta de grupo (“CUMPLIMIENTO”) en estilo caption: 13/18, 500, mayúsculas, tracking 0.02em, `muted-foreground`.

### Submenú

Todo elemento con páginas propias (`NavItem.children`, como NOM-037) se dibuja así; lo pinta `NavSubmenu` sobre `SidebarMenuSub` y `SidebarMenuSubButton`, y lo fija `tests/Browser/DesignSystemTest.php`.

- **Padre:** botón que abre y cierra (`aria-expanded`), sin fondo propio. Abierto: ícono en `sidebar-primary`, texto 600 y chevron hacia abajo; cerrado, chevron a la derecha. Empieza abierto en cualquiera de sus páginas. Con el sidebar plegado no hay lugar para el submenú: el padre es un enlace a su primera página.
- **Guía:** 1 px en `sidebar-border`, a 22 px (bajo el centro del ícono del padre); los hijos van a 9 px de ella y su texto alinea con la etiqueta del padre.
- **Hijo:** píldora de 40 px, 14/20, 400 en `muted-foreground`; hover en `sidebar-accent`. Cada uno ocupa un paso de 44 px y su área táctil lo cubre completo.
- **Hijo activo:** la única píldora (`sidebar-accent`), texto 600, marca de 2 px en `sidebar-primary` sobre la guía y `aria-current="page"`. Una página interna enciende al hijo del que cuelga (el expediente de un centro enciende “Centros”).

