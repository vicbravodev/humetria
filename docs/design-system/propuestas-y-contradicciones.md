# Propuestas y contradicciones

Lo que dicen los artifacts de rediseño en claude.ai y aún no está en la app, y dónde chocan entre sí o con la app.
**Nada de esto es regla:** la regla es la app y `design-system.md`. Una propuesta entra al design system cuando se
construye; hasta entonces un diseño nuevo no la da por hecha. Revisado contra el código el 2026-11-25.

## Propuestas pendientes

Ideas que solo existen en un artifact. Ninguna choca con la app ni con una regla del proyecto: se pueden construir si el
dueño las pide.

### Consola de superadmin — [artifact](https://claude.ai/artifact/S62HwE1En7zspjPhQPm8Ke)

1. Inicio «Hoy»: cola de lo que hay que atender, 4 KPI, MRR, embudo de demos y actividad reciente (hoy «Resumen» son
   `OverviewCard` en dos columnas).
2. Vistas guardadas como pestañas en Empresas («Prueba por vencer», «Saldo vencido», «Sin entrar 30 d»).
3. Columnas de MRR, uso de asientos, renovación y último acceso en la tabla de empresas.
4. Vista rápida en panel lateral y barra de acciones masivas en Empresas (`BulkBar` ya existe).
5. «Empresa 360»: tarjeta de encabezado, pestañas y columna derecha con contacto, notas internas, responsable y zona de
   peligro (hoy `pages/tenancy/company.tsx` es una sola columna).
6. Entrar como soporte con motivo y duración (30 min, 1 h, 2 h), y franja con cuenta regresiva y «Extender» (hoy es un
   enlace directo, «Entrar como owner»).
7. ⌘K que busca empresas, cuentas y personas de toda la plataforma.
8. Confirmar escribiendo el nombre antes de una acción destructiva.
9. Cobranza por lotes y saldos por antigüedad.
10. Pantallas nuevas: Salud del sistema, Mensajería, Planes y precios, Avisos de mantenimiento.
11. Alerta de límite al 90 % y exportar o borrar los datos de una empresa.

### Portal: Inicio por momento del día — [artifact](https://claude.ai/artifact/6gwxkgVETpXheSr5xNkzk6)

12. Fila de 3 accesos rápidos en Inicio (Mi semana, Vacaciones, Corrección).
13. Pendientes ordenados por fecha límite, «Respondidas esta semana» y «Actualizado hace un momento».
14. En Cuenta, el grupo «Este teléfono» (avisos y «App instalada · Al día») y enlaces a Políticas, Teletrabajo y Ley Silla.
15. Saldo de vacaciones como anillo (hoy es una cifra grande en tarjeta, `components/leave/portal-balance.tsx`).

### Humi — [asistente](https://claude.ai/artifact/SKPsMLTzj4EHCeYteiFUew) y [panel](https://claude.ai/artifact/C5HwHut3sVx4XrqHwnSfGy)

16. Que una respuesta con nivel de riesgo encienda ese número de marcas en Humi (el prop `ticks` existe en
    `lib/assistant/humi-expressions.ts`, pero nadie lo pasa).
17. La línea «Enter envía» al pie de la caja.
18. La hora en cada respuesta.
19. Un bloque «Siguientes pasos» con varias acciones (hoy es un solo botón en `HumiAnswerFooter`).

### Páginas de producto y landing — [productos](https://claude.ai/artifact/XUMFYPG2sR8VFe1dSFryRn) y [landing v2](https://claude.ai/artifact/EtCxB53uRuhYbmvjiMybVU)

20. Cuarta columna «¿No sabes qué te toca?» en el menú de herramientas (hoy dice «Ver las nueve herramientas»).
21. Sección del canal de denuncias (referencia y clave) en la landing.

### Rediseño de pantallas — [artifact](https://claude.ai/artifact/GzkXy7q5GBy7yt5ozewtZK)

22. El aviso «3 personas no tienen correo ni celular» en Empleados (`Notice` ya existe).
23. Botón secundario «Buscar ⌘K» en la página 404.
24. Enlace «Elige qué avisos recibir» en el correo semanal.

### NOM-037, Ley Silla y ajustes — [artifact](https://claude.ai/artifact/Mabe2mH9pPgE8FKA2BvY6Q)

25. Lista de clientes del consultor (tablero V2-Consultor). Ver también la contradicción de «Agregar cliente».

## Contradicciones

Dónde los artifacts no coinciden entre sí o con la app. En todas manda la app; la columna dice qué hace.

| Tema | Lo que dicen los artifacts | Lo que hace la app |
|---|---|---|
| White label | El DS de claude.ai (2026-10-27) y el tablero de marca de NOM-037: «el consultor solo cambia el logo». | Logo y acento (`brand_color` por `BrandPalette`), desde el PR #171. |
| Color del sidebar | El DS de claude.ai y los lienzos de consola y roles: `#F5F8F7` / `#121B1A`. | `#F2F6F5` / `#0B1211` (`resources/css/app.css`, `tokens.json`). |
| Profundidad | El DS de claude.ai: una sola sombra, `shadow-float`. | Dos niveles: `shadow-raised` (panel de la página) y `shadow-float`. |
| Arriba del sidebar | Sidebar con submenú: logo de Humetría. Rediseño de pantallas: la empresa con su número de personas. | `CompanyMark` y debajo `TenantSwitcher`. |
| Fila activa del sidebar | Sidebar con submenú: relleno `sidebar-accent`. Rediseño de pantallas: tarjeta con anillo. | Tarjeta: fondo `card` y anillo de 1 px. |
| Títulos de grupo del menú | «CUMPLIMIENTO» en caption mayúsculas (Sidebar, NOM-037); «Cumplimiento / Herramientas». | `NavGroup` plegables con ícono: Cumple la norma, Escucha a tu gente, Ordena tu operación, Administración. |
| Menú en el celular | Sidebar: cajón de 300 px. Rediseño de pantallas: el sidebar se vuelve una fila a 900 px. | `MobileTabBar` abajo y el sidebar como `Sheet` desde «Más», bajo 768 px. |
| Contadores del menú | Acento (panel v1), rojo (Asistencia), rojo solo al vencer (tablero V4). | Cifra en `primary` sobre `accent`, a la derecha de la fila (evaluaciones en curso de NOM-035); nunca rojo. |
| Badge | Consola y roles: rectángulo de 6 px sin punto. Asistencia: chip con borde. Clima: píldora con punto. | Píldora `rounded-full` con punto (`components/ui/badge.tsx`, `componentes/Badge.md`). |
| Tamaño de texto base | Consola: 14 px. | 16 px en todo el panel. |
| Radio de las tarjetas | 10 px (Clima), 12 px (panel v1), 14 px (Asistencia, NOM-037). | 14 px (`radius-xl`). |
| Pestañas | 44 px con 4 px de relleno (Clima) contra 38 px con 3 px (Asistencia). | 38 px, como Asistencia (`CountTabs`). |
| Cifra protegida | Caja punteada (Clima). | Candado y «Protegido» (`ProtectedCell`). |
| Trabajo por estado | Compromisos de clima y medidas de Ley Silla como kanban. | Compromisos en pestañas con lista paginada (`commitments-board.tsx`); medidas en tres columnas (`measures-board.tsx`). Las dos conviven; no se propone cambiarlas. |
| Roles | NOM-037: tres roles fijos con matriz de permisos. Roles y permisos: editor de roles propios. | Editor de roles (`pages/tenancy/roles.tsx`, `components/tenancy/role-editor-form.tsx`). |
| Requisitos de Ley Silla | 13 cuadros R1–R13. | Arco más lista (`components/seating/requirements-board.tsx`). |
| Zonas de asistencia | Se editan en una tarjeta en línea. | En un diálogo, porque el mapa necesita el ancho (`zone-dialog.tsx`). |
| Dispositivos de la persona | Página «Dispositivos» con Enlace / Acuerdo / Llave por persona. | «Accesos» con pestañas (`pages/worker-access/access.tsx`). |
| Panel de inicio | Rediseño de pantallas: «Lo que sigue» y «Ciclo de cumplimiento». | Lo urgente, las rutas con su `StageBar` y el siguiente paso (`pages/dashboard.tsx`, tablero V2-Panel). |
| Usuarios | Panel «Qué puede hacer cada rol» dentro de Usuarios. | Página aparte de Roles. |
| Canal SMS | Rediseño de pantallas lo ofrece para invitar. | No hay SMS: correo y WhatsApp (regla del proyecto). |
| Alta de clientes del consultor | NOM-037 (V3-Alta): «Agregar cliente». | El alta de empresas es solo por demo, desde la consola. |
| Pestañas del portal | Navegación inferior (aprobada el 2026-11-16): tres, iguales para todos. Inicio por momento: cuatro, con Marcar. | Cuatro con Marcar, y tres cuando la empresa no tiene asistencia; Marcar es un enlace que sale de la barra. |
| Pestaña actual del portal | Inicio por momento: relleno `accent`. Navegación inferior: `primary` sólido. | `primary` sólido. |
| Barra en las páginas de herramienta del portal | Inicio por momento: sí. Navegación inferior: nunca. | Nunca; solo en el inicio. |
| Encabezado del portal | Inicio por momento: sin barra, avatar arriba a la derecha. Navegación inferior: empresa y «Mi portal». | Empresa y «Mi portal» (`PersonFrame`). |
| Escala del portal | Inicio por momento: títulos de 32 px en 700, botones de 64 px, presionar a 0.94. | Títulos de 24 px en 600, botones de 56 px, presionar a 0.99. |
| Prueba reciente en el portal | Hoja desde abajo. | Diálogo centrado (`recent-proof-dialog.tsx`). |
| Avatar de Humi | Panel (3-nov): cara en un cuadrado. Asistente (16-nov): personaje con arco. | Personaje con arco (`Humi`, `HumiAvatar`). |
| Contexto de Humi | Panel: selector y franja «Estás viendo…». Asistente: chip «Viendo: …». | Chip (`HumiContextChip`). |
| Inicio de Humi | Panel: cuadrícula de 2×2 con íconos. Asistente: lista con datos vivos. | Lista (`HumiStarters`). |
| Aviso de Humi | «Revisa las cifras» (asistente) contra «Verifica las cifras antes de reportarlas» (panel). | «Verifica las cifras antes de reportarlas». |
| Lanzador de Humi | 56 px (panel). | 48 px (`HumiLauncher`). |
| Mínimo por grupo en Humi | «grupos de 5 personas o más» (asistente). | «el mínimo de personas que fijó cada evaluación»: el mínimo lo configura la empresa. |
| Landing | Landing v2: hero con tarjeta de producto, pestañas de 4 herramientas, «Agendar demo». Páginas de producto: «Tu gente contesta desde el celular…», cuatro reglas. | Sigue a páginas de producto, con su propio titular («Cumple la ley y cuida a tu gente desde un solo lugar.»), video en el hero, tres reglas con demo de privacidad, Precios y «Agenda una demo». |
| Color de cada herramienta | Páginas de producto: todo en jade. | Color por familia (`.family-*`) en el sitio público; las acciones siguen en `primary`. |

## Para un diseño nuevo

- **Elegir de una lista corta:** unos 25 archivos de módulos rehacen el estilo de tarjeta elegida a mano (por ejemplo
  `components/engagement/pulse/mode-options.tsx`, `components/compensation/option-cards.tsx`). No se tocan, pero una
  pantalla nueva usa `ChoiceField`, `OptionRows` o `PillChoice`.
- **Ajustes:** las pantallas de ajustes de Asistencia, Ley Silla, NOM-037 y Quejas usan `SettingRow`; la de políticas
  de Vacaciones no. Una pantalla nueva de ajustes usa `SettingRow`.
- **«Formulario móvil»** ([artifact](https://claude.ai/artifact/1pPNQihdvgejZHiAvKE5or)) trata del formulario «Agenda una
  demo» de la landing, no del portal. Ya está en la app (`components/landing/demo-section.tsx`); la app conserva los
  pasos completos y la frase de entrada larga que el artifact recortaba.
