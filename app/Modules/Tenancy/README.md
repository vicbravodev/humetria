# Tenancy (empresas, miembros, roles y permisos)

Empresas, centros de trabajo, miembros con su rol, herramientas y límites por empresa (`CompanyModules`, `CompanyLimits`).
Este README cubre lo que no se ve leyendo una sola clase: **quién puede hacer qué dentro de una empresa**. Diseño:
`docs/superpowers/specs/2026-11-23-granular-permissions-design.md`; cómo se le explica a la empresa (y a Humi):
`docs/normas/permisos/criterios.md`.

## Permisos: catálogo, roles y extras

- **Catálogo** (`Support/PermissionCatalog`): cada permiso del panel con su etiqueta, módulo, si es `sensitive` (pide
  confirmación y lo marca la bitácora), si es `ownerOnly` (`manage-members`: no se reparte) y la herramienta que exige
  (`requiresModule`). Una prueba ata el catálogo a la tabla `permissions`: un permiso nuevo sin entrada rompe el CI.
- **Plantillas**: owner, hr, viewer, committee y complaints (`Enums/TenantRole`) son roles de Spatie con `tenant_id` nulo.
  No se editan; las migraciones que agregan permisos solo llegan a ellas, nunca a las copias.
- **Roles propios**: filas de `roles` con el `tenant_id` de la empresa, nombre interno `custom_<uuid>` (ninguna etiqueta
  choca con una plantilla), `label` (lo que escribió el dueño) y `based_on` (informativo). Máximo
  `tenancy.max_custom_roles` (20). Un rol con personas o invitaciones sin aceptar no se borra. Todo pasa por
  `Support/CompanyRoles`, que cambia al _team_ de Spatie de la empresa y deja el de la petición como estaba.
- **Una persona, un rol** por empresa, más **permisos directos** (`model_has_permissions` con `tenant_id`): solo suman.
  `CompanyRoles::effectivePermissions` da el resultado (rol + extras) sin los de herramientas que el plan no incluye; uno
  así se guarda y vuelve a valer si la herramienta regresa.
- **Áreas** de la pantalla «Roles y permisos»: un nivel por área y algunos extras. Están escritas en
  `tests/Fixtures/permissions/areas.json`; `resources/js/lib/permission-areas-data.ts` (la pantalla) y
  `Support/PermissionAreas` (Humi) se prueban contra ese archivo (`PermissionAreasTest`, `permission-areas.test.ts`), y el
  archivo contra el catálogo y las plantillas. Un área nueva va en los tres, o algún test falla.
- **Solo el dueño administra** roles y permisos (`manage-members`). Suplantando al dueño, soporte ve las pantallas pero no
  cambia nada: las escrituras están en `ForbidWhileImpersonating`.
- Cambios de rol o de extras emiten `MemberRoleChanged` / `MemberPermissionsChanged` (Complaints aplica el conflicto C-45),
  escriben log (`tenant_role.*`, `tenant_member.*`) y van a la bitácora.

## Ninguna ruta sin permiso

- `Support/RoutePermissions` dice qué permiso pide cada ruta `tenant.*`, por nombre (el primer patrón que coincide gana) y
  `Http/Middleware/RequireRoutePermission` lo exige. El `Gate` del FormRequest o del controlador sigue como segunda barrera.
- **Una ruta `tenant.*` nueva va en `RoutePermissions`** (o en su lista `EXEMPT`, con su razón): si no,
  `RoutePermissionCoverageTest` falla en el CI. `TemplateAccessUnchangedTest` cuida que las plantillas no ganen ni pierdan
  páginas.
- Lo que el plan no incluye lo cortan antes `LimitToEnabledModules` y `LimitUnionToCompensation`: el permiso no lo sustituye.

## Adónde llega cada quien

`Support/StartRoute` elige la primera página del menú que la persona puede abrir (cada candidata pregunta por el permiso de
su propia ruta, así que nunca aterriza en un 403). Sin ninguna, `NoAccessController` muestra «Aún no tienes acceso» y a
quién pedirlo. El panel (`tenant.dashboard`, exento del mapa) se abre con `view-dashboard` y `view-work-centers`
(`StartRoute::PANEL_NEEDS`); a quien le falta uno lo manda a su `StartRoute` (así entra también desde SSO o un correo).

## Humi

Lee esto por `Contracts/CompanyAccess` (`Support/CompanyAccessReader`): al dueño, cuántos roles propios hay (de 20), cuántas
personas tiene cada rol y cuántas tienen extras; a cualquiera, su propio nivel por área. Conteos y niveles, nunca quién
tiene qué (`AccessReaderTest`).
