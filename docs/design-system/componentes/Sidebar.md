Navegación lateral del panel de RH; usa el `Sidebar` de shadcn con los tokens `sidebar-*`.

**Recibe:** `current` (elemento activo), `items` (opcional: `{icon, label, count}`), `user`, `org`, `initials`.

- El logo de Humetría va arriba; en white label lo reemplaza el logo del consultor y Humetría no aparece.
- Elemento activo: fondo `sidebar-accent`, texto en 600 e ícono en `sidebar-primary`.
- Un solo botón primario (“Nueva campaña”) y máximo 7 elementos.
- En escritorio es el marco de la app (`variant="inset"`): la página va en un panel con radio `radius-xl`, borde `sidebar-border` y `shadow-raised`.
- Se pliega a sus íconos (`collapsible="icon"`) con el botón de la barra superior del panel o con ⌘B (Ctrl+B); recuerda el estado en la cookie `sidebar_state`. Plegado, el logo pasa al símbolo, cada elemento conserva su área de 44 × 44 px y su nombre aparece como tooltip.
- La barra superior del panel se queda arriba al desplazar la página y muestra un divisor cuando el contenido pasa por debajo. A la derecha lleva el selector de tema (Claro, Oscuro, Sistema): un grupo de radios con un chip elevado que se desliza al elegido.
- En móvil se pliega en un `Sheet` desde la izquierda.
