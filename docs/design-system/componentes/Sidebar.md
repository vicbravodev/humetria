Navegación lateral del panel de RH; usa el `Sidebar` de shadcn con los tokens `sidebar-*`.

Lo pinta `AppSidebar` (`components/app-sidebar.tsx`); los grupos y sus elementos salen de `lib/nav-sections.ts`. El marco completo (barra superior, panel, celular) está en `design-system.md`, «Marcos».

- Arriba va `CompanyMark`: el logo de la empresa (white label) o sus iniciales; fuera de una empresa, el de Humetría. Debajo, `TenantSwitcher`, la tarjeta de 56 px de la empresa.
- **Grupos:** el primero sin título; los demás (Administración, Cumple la norma, Escucha a tu gente, Ordena tu operación) son `NavGroup`: fila plegable de 44 px con el ícono de la familia, texto 600 y chevron que gira 90° al abrir; sus herramientas cuelgan de una guía de 1 px en `sidebar-border`. Empieza abierto si contiene la página actual; plegado, muestra la suma de los contadores.
- **Elemento:** 44 px, ícono de 20 px en `muted-foreground`, texto 16 px en 400. **Activo:** fondo `card`, anillo de 1 px en `sidebar-border`, peso 500 e ícono en `sidebar-primary`. Hover en `secondary`.
- El contador de un elemento (evaluaciones en curso) va a la derecha, en `primary` sobre `accent` (`SidebarMenuBadge`).
- En escritorio es el marco de la app (`variant="inset"`): la página va en un panel con radio `radius-xl`, borde `sidebar-border` y `shadow-raised`.
- Se pliega a sus íconos (`collapsible="icon"`) con el botón de la barra superior del panel o con ⌘B (Ctrl+B); recuerda el estado en la cookie `sidebar_state`. Plegado, cada elemento conserva su área de 44 × 44 px y su nombre aparece como tooltip; los títulos de grupo y `NextObligationCard` se ocultan.
- Al pie, `NextObligationCard` (con una evaluación en curso) y `NavUser`, la fila de la cuenta que abre su menú hacia arriba.
- En una pantalla de 900 px de alto o menos (desde `md`), las filas se aprietan a 36 px.
- En el celular la navegación principal es `MobileTabBar` abajo; el sidebar solo se abre como `Sheet` desde «Más».

### Submenú

Todo elemento con páginas propias (`NavItem.children`, como NOM-037) se dibuja así; lo pinta `NavSubmenu` sobre `SidebarMenuSub` y `SidebarMenuSubButton`, y lo fija `tests/Browser/DesignSystemTest.php`.

- **Padre:** botón que abre y cierra (`aria-expanded`), sin fondo propio. Abierto: ícono en `sidebar-primary`, texto 600 y chevron hacia abajo; cerrado, chevron a la derecha. Empieza abierto en cualquiera de sus páginas. Con el sidebar plegado no hay lugar para el submenú: el padre es un enlace a su primera página.
- **Guía:** 1 px en `sidebar-border`, a 22 px (bajo el centro del ícono del padre); los hijos van a 9 px de ella y su texto alinea con la etiqueta del padre.
- **Hijo:** píldora de 40 px, 14/20, 400 en `muted-foreground`; hover en `sidebar-accent`. Cada uno ocupa un paso de 44 px y su área táctil lo cubre completo.
- **Hijo activo:** la única píldora (`sidebar-accent`), texto 600, marca de 2 px en `sidebar-primary` sobre la guía y `aria-current="page"`. Una página interna enciende al hijo del que cuelga (el expediente de un centro enciende “Centros”).

