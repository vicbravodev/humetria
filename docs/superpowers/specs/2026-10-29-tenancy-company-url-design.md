# Tenancy: la empresa en la URL, el bloqueo y la impersonación

Fecha: 2026-10-29. Estado: propuesta.

## Intención

Hoy el owner acepta su invitación (#37) y cae en un `/admin` genérico que no sabe de qué empresa es. Nada de lo que
viene (centros de trabajo, empleados, campañas) puede construirse sin saber en qué empresa está la persona. Este PR hace
que **cada pantalla de una empresa viva bajo `/admin/{slug}`**, que la empresa se resuelva desde la URL y quede en el
`Context` de cada log, y que una empresa bloqueada (prueba terminada, periodo vencido o desactivada) **no se pueda usar**.

Además, el superadmin necesita ver y operar una empresa exactamente como su owner para dar soporte. Lo hace
**impersonando** al owner, y todo lo que hace así deja rastro.

Es el quinto paso del orden acordado para Tenancy: primer superadmin (#28) → el superadmin crea la empresa (#34) →
invitar al owner (#37) → **resolver la empresa desde la URL** → centros de trabajo → el owner invita usuarios → el
superadmin crea superadmins.

## Decisiones del usuario

1. **Entrada a `/admin`:** con una sola empresa entra directo a `/admin/{slug}`. Con varias ve una lista de sus empresas
   y elige. Sin ninguna ve "Aún no tienes acceso a ninguna empresa. Contacta al administrador de Humetría" con
   "Cerrar sesión".
2. **Selector de empresa** arriba del sidebar con el nombre comercial de la empresa actual.
3. **El superadmin entra a una empresa impersonando literalmente al owner**: se vuelve esa persona y ve lo mismo que
   ella, incluida (cuando exista) la lista de canalización de Guía I. Siendo él mismo, sin impersonar, no entra a
   `/admin/{slug}`.
4. **Al impersonar puede hacer todo** lo que puede el owner. Sus acciones quedan registradas como las de un usuario
   normal (actor = el owner) **y además** queda el rastro de que fue una impersonación: en los logs y en la bitácora.
5. **Bajo la empresa** vive el Panel (`/admin/{slug}`). Configuración (`/admin/settings/…`), Empresas y Demos se quedan
   donde están.
6. **Bloqueo:** un modal grande que no se puede cerrar, con "Cerrar sesión" (y "Cambiar de empresa" si tiene otras). Texto:
   > **El acceso a {nombre comercial} está en pausa**
   > Puede deberse a un pago pendiente o a que terminó tu periodo de prueba o de servicio. Para reactivarlo, contacta
   > al administrador de Humetría.
7. **El superadmin impersonando sí ve el modal, pero lo puede cerrar** y seguir trabajando.
8. **Días de prueba:** junto al nombre de la empresa, "Prueba · quedan N días", solo mientras la empresa está en prueba.
9. **Un solo PR** con todo esto, impersonación incluida.

## Objetivos

1. Un owner con una empresa inicia sesión y llega a `/admin/{slug}`. Con varias, elige de una lista y cambia de empresa
   desde el sidebar.
2. Nadie ve una empresa de la que no es miembro: `/admin/{slug}` ajeno, inexistente o de un superadmin sin impersonar
   responde **404**.
3. Cada línea de log y cada fila de bitácora dentro de una empresa lleva su `tenant_id`.
4. Una empresa bloqueada muestra el modal en cualquiera de sus pantallas, y el servidor rechaza cualquier acción sobre
   ella, no solo la tapa.
5. El superadmin pulsa "Entrar como owner" en la consola, trabaja como el owner con una franja fija que lo recuerda, y
   regresa a su sesión con "Salir". El inicio, el fin y cada acción intermedia quedan ligados a él en logs y bitácora.
6. Mientras la empresa está en prueba, el owner ve cuántos días le quedan.

## No objetivos (llegan en otros PRs)

- Modelos con `tenant_id` y su global scope: el primero es "centros de trabajo", el PR siguiente. Ahí se decide el
  contrato público para que otros módulos lean la empresa actual.
- Roles `hr` y `viewer`, y que el owner invite usuarios.
- Impersonar a alguien que no sea owner (no existen otros roles todavía).
- Que el superadmin entre a una empresa siendo él mismo, o a una empresa que aún no tiene owner.
- Recordar la última empresa usada.
- Un correo o teléfono de soporte en el modal.

## Anti-objetivos

- **Que una persona vea o toque datos de una empresa ajena**, ni por URL ni por un `slug` adivinado. 403 delataría que
  existe: siempre 404.
- **Que una empresa bloqueada siga operando** por una petición directa (un `POST` hecho a mano, una pestaña vieja).
- **Que una acción hecha impersonando parezca hecha por el owner sin más**: siempre se puede saber qué superadmin fue.
- **Que impersonar sirva para apropiarse de la cuenta del owner** (cambiar su contraseña, su correo, su 2FA o borrarla).
- Que un usuario que no es superadmin pueda impersonar, o que alguien impersone a un superadmin.
- Que una sesión de impersonación quede "atorada": "Salir" siempre regresa al superadmin original.

## Flujo

```
Inicia sesión (Fortify, home = /admin) ──► /admin
  superadmin sin impersonar ──► consola Empresas
  0 empresas ──► "Aún no tienes acceso a ninguna empresa…" + Cerrar sesión
  1 empresa  ──► /admin/{slug}
  2+         ──► lista "Elige una empresa" (nombre comercial + estado) ──► /admin/{slug}

/admin/{slug}/…
  slug inexistente, no es miembro, o superadmin sin impersonar ──► 404
  empresa bloqueada:
     GET   ──► la página con el modal de bloqueo encima (sin datos de la empresa) — no se puede cerrar
     otro  ──► rechazado
  empresa en prueba ──► "Prueba · quedan N días" junto al nombre

Consola Empresas, fila con owner Activo ──► "Entrar como owner"
  ──► la sesión pasa a ser del owner (con la marca de impersonación) ──► /admin (resuelve como el owner)
  franja fija: "Estás viendo Humetría como {nombre del owner} · Salir"
  empresa bloqueada ──► ve el modal y lo puede cerrar; sus acciones no se rechazan
  "Salir" ──► la sesión vuelve al superadmin ──► consola Empresas
```

## Decisiones de Claude (revisables)

### Rutas

- `GET admin` (`dashboard`) deja de pintar el Panel y pasa a **resolver adónde ir** según la decisión 1. Fortify y
  "aceptar invitación" siguen redirigiendo ahí, así que no cambian.
- `GET admin/{tenant}` pinta el Panel de esa empresa. `{tenant}` se resuelve **por `slug`**. Todas las rutas futuras de
  una empresa van en el mismo grupo `admin/{tenant}/…`, con el middleware que resuelve la empresa.
- La lista "Elige una empresa" y la página "sin empresas" son la respuesta de `GET admin` en cada caso, no rutas aparte.
- Los slugs reservados (`TenantRules::RESERVED_SLUGS`) ya cubren las rutas de `/admin`. Si este PR agrega un segmento
  nuevo bajo `/admin` (por ejemplo `impersonation`), se reserva, y el test que recorre `/admin` lo exige.

### Resolver la empresa

- Un middleware del módulo Tenancy, en el grupo `admin/{tenant}`, responde **404** si el usuario no tiene membresía en
  esa empresa. **No usa un Gate ni una Policy**: el `Gate::before` del superadmin lo dejaría pasar, y la decisión 3 dice
  que el superadmin solo entra impersonando.
- Si pasa, hace `Context::add('tenant_id', …)`. Desde ahí cada log, cada fila de `RecordAudit` y cada job en cola lleva
  la empresa. (`CLAUDE.md` pedía ponerlo en `AssignRequestContext`, pero el kernel no puede depender de un módulo: se
  pone en el middleware de Tenancy y se corrige esa línea de `CLAUDE.md`.)
- Comparte a Inertia la empresa actual: `slug`, nombre comercial, estado, si está bloqueada y, si está en prueba, los
  días que le quedan. También comparte la lista de empresas del usuario para el selector (`slug` y nombre comercial).

### Bloqueo

- Bloqueada = `TenantStatus::isBlocked()` (Prueba terminada, Vencida, Desactivada), que ya existe.
- Para un miembro normal, una petición `GET` a cualquier ruta de la empresa responde con el **frame de la app y el
  modal**, sin las props de la página (no se filtran datos detrás del modal). Cualquier otro método responde **423**
  sin tocar nada.
- Para un superadmin impersonando, el bloqueo no se aplica en el servidor: la página carga normal y el modal aparece
  con un botón para cerrarlo.
- El modal sigue la guía `Dialog` del design system. Con una sola empresa ofrece "Cerrar sesión". Con varias, también
  "Cambiar de empresa", que lleva a la lista.

### Días de prueba

- `quedan N días` = días completos que faltan para `trialEndsAt()`, **redondeando hacia arriba**: con 13 días y 2 horas
  restantes dice 14. El último día dice "Prueba · último día". Una prueba que ya terminó no muestra etiqueta: está
  bloqueada.
- El cálculo es lógica pura con su test unitario y `mutates()`.

### Impersonación

- **Quién:** solo un superadmin, y solo sobre el **owner activo** de una empresa (membresía `owner`). No se puede
  impersonar a otro superadmin, ni impersonar estando ya impersonando. El botón "Entrar como owner" aparece en la fila
  de la consola solo cuando el owner está *Activo*.
- **Rutas:** `POST admin/companies/{tenant}/impersonate` (consola, con el Gate `manage-tenants`, 404 para los demás) y
  `POST admin/impersonation/stop` (con `auth`, 404 si la sesión no está impersonando).
- **Cómo:** se inicia sesión como el owner (`Auth::login`), se regenera el id de sesión y se guarda en la sesión el id
  del superadmin original. "Salir" vuelve a iniciar sesión como ese superadmin, **solo si sigue siendo superadmin**
  (si no, cierra la sesión), regenera el id y lleva a la consola Empresas. Cerrar sesión estando impersonando cierra todo.
- **Cuenta del owner protegida:** mientras se impersona, **Configuración → Perfil y Seguridad** (nombre, correo,
  contraseña, 2FA, passkeys, borrar la cuenta) responden 404 y no aparecen en el menú. Apariencia sí queda.
  *Esta es una restricción de Claude sobre "puede hacer todo": protege la cuenta personal del owner, no los datos de
  la empresa.*
- **Franja:** fija arriba del frame, "Estás viendo Humetría como {nombre} · Salir", visible en toda pantalla mientras
  dura. Se comparte a Inertia un `impersonator` (nombre del superadmin) o `null`.
- **Rastro en logs:** `AssignRequestContext` agrega `impersonator_id` al `Context` cuando la sesión trae la marca. Así
  cada línea de log y cada job llevan `user_id` (el owner) **e** `impersonator_id` (el superadmin). La llave de sesión
  vive en el kernel, porque impersonar es de usuarios, no de empresas.
- **Rastro en bitácora:** columna nueva `audit_entries.impersonator_id` (nullable, con índice), que `RecordAudit` toma
  del `Context`. La tabla sigue siendo de solo inserción. **Sin llave foránea**, igual que `actor_id`: con una FK, borrar
  la cuenta de un superadmin que impersonó chocaría con el trigger de solo inserción.
- **Se revisa en cada petición:** mientras dure la impersonación, si el superadmin original ya no existe o ya no es
  superadmin, la sesión se cierra en esa misma petición (log `impersonation.revoked`). Así quitarle el rol corta su
  acceso de inmediato, no hasta que pulse "Salir".
- **Eventos:** `impersonation.started` e `impersonation.stopped`, en log y bitácora. Sujeto: el usuario impersonado.
  `metadata`: solo ids (`tenant_id` de la fila desde la que entró). Nunca nombres ni correos.

### Logs y bitácora

- `tenant.blocked_request_rejected` (log, no bitácora) cuando se rechaza un método no-`GET` sobre una empresa bloqueada.
- Entrar a una empresa o cambiar de empresa no escribe nada: es lectura.

### Panel y navegación

- El Panel actual (estado vacío "Aún no hay campañas") pasa a `admin/{tenant}` tal cual.
- `mainNavItems` deja de ser una lista fija: sus enlaces llevan el `slug` de la empresa actual (Wayfinder con parámetro).
  Fuera de una empresa (consola, configuración) el enlace "Panel" lleva a `/admin`.
- El selector del sidebar muestra el nombre comercial y la etiqueta de prueba. Con 2+ empresas abre la lista para
  cambiar; con una sola es texto.

### Pruebas

**Unit, con `mutates()`:**
- Días de prueba restantes en los bordes: recién iniciada (14), 13 días y 1 segundo (14), exactamente 1 día (1), menos
  de un día ("último día"), terminada (nada).

**Feature:**
- `GET admin`: superadmin → consola; 0, 1 y 2 empresas → cada caso; una empresa bloqueada sigue apareciendo en la lista.
- `GET admin/{slug}`: miembro → Panel con la empresa en las props; empresa ajena, slug inexistente y superadmin sin
  impersonar → 404; sin sesión → login.
- `Context`: dentro de una empresa, una entrada de `RecordAudit` y una línea de log llevan su `tenant_id`.
- Bloqueo, para las tres causas: `GET` trae el estado bloqueado **sin** las props de la página; `POST` responde 423 y
  escribe `tenant.blocked_request_rejected`.
- Impersonación: el superadmin entra, la sesión es del owner, la bitácora tiene `impersonation.started` con
  `impersonator_id`; una acción durante la impersonación (por ejemplo, actualizar Apariencia o cualquier ruta que
  escriba bitácora) queda con actor = owner e `impersonator_id` = superadmin; "Salir" regresa al superadmin y escribe
  `impersonation.stopped`.
- Impersonación prohibida: un no superadmin → 404; empresa sin owner activo → 404; impersonar estando impersonando →
  404; owner que es superadmin → 404; `stop` sin impersonar → 404.
- Perfil y Seguridad responden 404 impersonando y 200 sin impersonar.
- Al superadmin original le quitan el rol (o se borra su cuenta) a media impersonación: la siguiente petición cierra la
  sesión y escribe `impersonation.revoked`.
- Empresa bloqueada impersonando: `GET` trae la página completa con el estado bloqueado y la marca de impersonación;
  `POST` no se rechaza.
- Las props de Inertia de las páginas nuevas no traen nada más que lo descrito arriba.

**Browser (`visitPage`, claro, oscuro y móvil):**
- Panel de la empresa, lista "Elige una empresa", página "sin empresas", modal de bloqueo y la franja de impersonación.
- Flujo: el superadmin entra como owner desde la consola, ve la franja, sale y vuelve a la consola.
- Flujo: un owner con dos empresas cambia de una a otra desde el selector.

### Commits (un comportamiento cada uno)

1. Panel bajo `admin/{tenant}` con membresía, 404 y `tenant_id` en `Context`.
2. `/admin` resuelve: superadmin, 0, 1 o varias empresas.
3. Selector de empresa en el sidebar.
4. Bloqueo: modal, estado sin props y 423.
5. Días de prueba.
6. `impersonator_id` en `Context` y en la bitácora (y la revisión en cada petición).
7. Entrar como owner y salir (franja incluida).
8. Proteger la cuenta del owner mientras se impersona.
9. El superadmin impersonando cierra el modal de bloqueo.

## Lo que se deja al constructor

- Nombres internos de middleware, acciones, DTOs, props y páginas Inertia, siguiendo los módulos de `CLAUDE.md`.
- Si "sin empresas" y "Elige una empresa" son una página Inertia o dos.
- El texto exacto de la lista, la franja y la página "sin empresas", dentro del tono del design system (el panel tutea).
- La composición de componentes respetando los límites del linter.
