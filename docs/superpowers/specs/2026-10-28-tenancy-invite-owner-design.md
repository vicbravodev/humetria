# Tenancy: el superadmin invita al owner y el owner entra

Fecha: 2026-10-28. Estado: propuesta.

## Intención

Hoy el superadmin da de alta una empresa (#34), pero nadie puede entrar a ella: no hay usuarios ligados a una empresa y
su prueba se queda "Sin iniciar". Este PR cierra ese hueco. Desde la consola "Empresas", el superadmin invita al
**owner** con su nombre y correo. El owner recibe un correo con un enlace de un solo uso, acepta, pone su contraseña y
entra. Ese primer acceso **inicia la prueba de 14 días**.

Es el tercer paso del orden acordado para Tenancy: primer superadmin (#28) → el superadmin crea la empresa (#34) →
**invitar al owner y que acepte** → resolver la empresa desde la URL → centros de trabajo → el owner invita usuarios →
el superadmin crea superadmins.

## Decisiones del usuario

1. **Dónde se invita:** con un botón "Invitar owner" en cada fila de la consola Empresas, que pide nombre y correo. La
   empresa se puede crear sin tener todavía el dato del owner.
2. **Un solo owner por empresa.** Si el owner se va, el superadmin lo cambia desde la consola. Los demás usuarios (RH,
   lectura) los invita el owner en un milestone posterior.
3. **Correo que ya tiene cuenta en Humetría** (por ejemplo, un consultor que ya es owner de otra empresa): la invitación
   solo lo agrega a la nueva empresa. Entra con su contraseña de siempre y no se crea una segunda cuenta.
4. **Vigencia del enlace: 7 días, de un solo uso.** El superadmin puede reenviar la invitación, lo que invalida el enlace
   anterior y reinicia los 7 días. También puede cancelar una invitación pendiente.
5. **Al aceptar**, el owner llena su nombre (precargado y editable) y su contraseña con confirmación. Abrir el enlace
   verifica el correo. El 2FA queda opcional, como hoy.
6. **La prueba arranca en el primer acceso del owner, salvo que la empresa ya esté activa.** Si el superadmin la activó
   con fecha antes de que el owner entrara, la prueba nunca arranca.
7. **Destino al aceptar:** el panel actual (`/admin`). La URL por empresa (`/admin/{slug}`) y el modal de bloqueo llegan
   en el PR siguiente.
8. **Consola:** una columna "Owner" con nombre, correo y estado: *Sin invitar*, *Invitación enviada (vence el …)*,
   *Vencida* o *Activo*. Desde ahí se reenvía o se cancela.
9. **Correo en desarrollo:** se usa **mailtrap-local**, el servicio que ya trae el `compose.yaml` (bandeja web en
   `http://localhost:3550`), en lugar del log. Nada sale de la máquina y los tests no cambian.

## Objetivos

1. El superadmin invita al owner de una empresa y ve en la consola que la invitación salió y cuándo vence.
2. Una persona nueva abre el enlace, pone nombre y contraseña, y queda dentro de Humetría como owner de esa empresa, con
   el correo verificado y la sesión iniciada.
3. Una persona que ya tiene cuenta abre el enlace, inicia sesión con su contraseña de siempre y queda como owner de
   una empresa más.
4. El primer acceso del owner pone la empresa "En prueba" por 14 días. Si la empresa ya estaba activa, no pasa nada.
5. El superadmin reenvía o cancela una invitación pendiente, y cambia de owner invitando a otra persona.

## No objetivos (llegan en otros PRs)

- Rutas `/admin/{slug}/…`, resolver la empresa desde la URL, `tenant_id` en `Context` y el **modal de bloqueo**. Mientras
  tanto, un owner aceptado ve el panel general `/admin`, igual que hoy cualquier usuario.
- Roles `hr` y `viewer`, y que el owner invite a otras personas.
- Decidir si los permisos por empresa usarán los *teams* de spatie/laravel-permission. Este PR solo registra quién
  pertenece a qué empresa y con qué rol (ver "Membresía").
- Cola de trabajos: el correo sale en el momento (la cola se dejó para después).
- Aviso de privacidad o términos al aceptar.
- Crear la invitación desde una solicitud de demo.

## Anti-objetivos

- **Que un enlace sirva dos veces**, o que siga sirviendo después de reenviarlo, cancelarlo o vencerse.
- **Que el token en claro quede guardado** en la base, en un log o en la bitácora.
- **Que una cuenta existente sea tomada** por quien tenga el enlace. Si el correo ya tiene cuenta, el enlace jamás pide
  ni cambia una contraseña: exige iniciar sesión como esa cuenta.
- **Que la prueba arranque dos veces**, o que arranque en una empresa ya activa o desactivada.
- Que la página del enlace revele si un correo tiene cuenta en Humetría a quien no tiene el enlace.
- Que alguien que no es superadmin sepa que existen las acciones de invitar (404, como el resto de la consola).
- Que un correo de desarrollo salga a internet.

## Flujo

```
Superadmin: "Invitar owner" (nombre, correo) ──► correo con enlace /admin/invitations/{token} (vence en 7 días)

El owner abre el enlace:
  token inválido, usado, cancelado o vencido ──► página "Este enlace ya no es válido. Pide uno nuevo al administrador de Humetría."
  el correo NO tiene cuenta ──► formulario: correo (solo lectura), nombre, contraseña, confirmación ──► Aceptar
  el correo SÍ tiene cuenta:
     sin sesión ──► "Inicia sesión para unirte a {empresa}" ──► login (Fortify, 2FA si lo tiene) ──► de vuelta al enlace
     con sesión de esa cuenta ──► "Únete a {empresa}" ──► Aceptar
  con sesión de una cuenta con OTRO correo (exista o no la cuenta invitada) ──►
     "Esta invitación es para {correo}. Cierra sesión para continuar." ──► botón que cierra la sesión y regresa al enlace

Aceptar (una transacción):
  crea el usuario (si no existe), con el correo verificado
  lo hace owner de la empresa (si había otro owner, ese pierde el acceso a esta empresa)
  marca la invitación como aceptada
  si la empresa está "Sin iniciar", pone trial_started_at = ahora
  inicia sesión ──► /admin
```

## Decisiones de Claude (revisables)

### Datos

**Invitaciones:** tabla `tenant_invitations` en el módulo Tenancy, con estas columnas:
- `id` (UUID)
- `tenant_id` (FK a `tenants`)
- `role` (por ahora solo `owner`, con `check`)
- `name`
- `email`, guardado en minúsculas
- `token_hash`: SHA-256 del token, `unique`
- `expires_at`
- `accepted_at` y `cancelled_at`, nullable
- `invited_by`: FK a `users`, quién la mandó o la reenvió
- `timestamps`

Todas las fechas son `timestampTz`.

- El token es aleatorio de 64 caracteres (`Str::random(64)`). Solo viaja en el correo; en la base vive su hash. Al
  abrir el enlace se busca por `hash('sha256', $token)`.
- Una invitación está **pendiente** si no se aceptó, no se canceló y `now < expires_at`. Si no se aceptó ni se canceló
  pero ya pasó `expires_at`, está **vencida**. El estado se calcula, igual que el de la empresa.
- **A lo más una invitación abierta (pendiente o vencida) por empresa y rol.** Invitar de nuevo cancela la abierta
  anterior en la misma transacción, esté vencida o no. En la base, un índice único parcial sobre `(tenant_id, role)`
  donde `accepted_at` y `cancelled_at` son nulos lo garantiza.
- **Reenviar** genera un token nuevo, reemplaza el hash y pone `expires_at = now + 7 días` en la misma fila. El enlace
  anterior deja de servir porque su hash ya no existe.
- **Cancelar** pone `cancelled_at`.
- Los 7 días son una constante del módulo, como los 14 de la prueba.

**Membresía:** tabla `tenant_memberships` con estas columnas:
- `id`
- `tenant_id` (FK)
- `user_id` (FK)
- `role` (`owner`, con `check`; `hr` y `viewer` llegan después)
- `timestamps`

Restricciones:
- `unique(tenant_id, user_id)`.
- Un índice único parcial sobre `tenant_id` donde `role = 'owner'`: un solo owner por empresa.

- Es la respuesta a "¿a qué empresas pertenece este usuario?", que necesita el PR de rutas por empresa. Si después los
  permisos usan *teams* de spatie, esta tabla sigue siendo la lista de miembros.
- `App\Models\User` (kernel) no conoce esta tabla: la relación vive del lado del módulo, como pide `CLAUDE.md`.

### Cambiar de owner

- Si la empresa ya tiene owner, el botón dice "Cambiar owner" y abre el mismo formulario. Mientras la invitación está
  pendiente, el owner actual sigue siendo owner.
- Al aceptar la nueva persona, la membresía del owner anterior **se borra** en la misma transacción: pierde el acceso
  a esta empresa. Su cuenta y sus otras empresas no cambian, y no se borra ningún dato de la empresa.
- Si la persona invitada ya es el owner actual, la validación lo rechaza con "Esta persona ya es el owner".

### Aceptar con una cuenta existente

- La página del enlace, con un token válido, muestra el nombre comercial de la empresa y el correo invitado. Quien
  tiene el enlace ya conoce ambos datos, así que mostrarlos no revela nada nuevo.
- Si el correo ya tiene cuenta, el botón lleva al login y guarda el enlace como "intended" para volver a él al entrar.
  El login es el de Fortify, con 2FA y passkeys si la cuenta los tiene.
- Aceptar con una cuenta existente **no** toca su contraseña, su nombre ni su verificación.
- Si hay sesión con un correo distinto al invitado, no se acepta nada, exista o no una cuenta con el correo invitado.
  Esto incluye al superadmin que abre el enlace para probarlo. La página lo dice y ofrece "Cerrar sesión y continuar".
  El logout de Fortify redirige siempre a `/` y perdería el enlace. Por eso el botón usa una ruta propia,
  `invitations.switch-account` (`POST admin/invitations/{token}/switch-account`, con `auth`), que cierra la sesión
  (invalida y regenera) y redirige al enlace.

### Inicio de la prueba

- `trial_started_at` se pone solo si la empresa está en **Sin iniciar**: `trial_started_at`, `active_until` y
  `deactivated_at` vacíos. Así se cumplen la decisión 6 y "la prueba nunca vuelve a empezar".
- Si la empresa ya está activa, desactivada, en prueba o con la prueba terminada, aceptar no cambia ninguna fecha.
  Cambiar de owner nunca reinicia la prueba.
- Se puede invitar y aceptar aunque la empresa esté bloqueada. El bloqueo se aplica con el modal del PR siguiente.

### Rutas

**Consola**, con autenticación y el Gate `manage-tenants`. `{tenant}` e `{invitation}` se resuelven por UUID, y una
invitación de otra empresa da 404:
- `companies.invitations.store`: `POST admin/companies/{tenant}/invitations`
- `companies.invitations.resend`: `POST admin/companies/{tenant}/invitations/{invitation}/resend`
- `companies.invitations.cancel`: `DELETE admin/companies/{tenant}/invitations/{invitation}`

**Públicas**, con `throttle` y agregadas a la lista pública de `RouteSecurityTest`. La razón: quien recibe la invitación
todavía no tiene sesión.
- `invitations.show`: `GET admin/invitations/{token}`
- `invitations.accept`: `POST admin/invitations/{token}`
- `invitation` y `invitations` se agregan a la lista de slugs reservados de `Tenant`. El test que recorre `/admin` lo exige.

### Correo

- Una `Notification` de Laravel enviada al correo invitado (`Notification::route('mail', …)`). El contrato es
  el `Mailer` de Laravel y su fake es `Notification::fake()`, así que no hace falta un contrato propio.
- Asunto: "Te invitaron a Humetría como owner de {nombre comercial}". El cuerpo trata de "tú", con el botón "Aceptar
  invitación" y la vigencia ("El enlace vence el 4 nov 2026"). Usa la plantilla de notificaciones de Laravel
  (`MailMessage`) sin publicarla; su saludo y su pie se traducen en `lang/es`. Una plantilla con la marca queda para después.
- Se envía después de que la transacción se confirma (`afterCommit`).

### Desarrollo con mailtrap-local

- `.env.example`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailtrap-local`, `MAIL_PORT=3535`. `phpunit.xml` ya fija
  `MAIL_MAILER=array` para los tests, y se confirma que siga así.
- `CLAUDE.md` → "Entorno local": "El correo local va a mailtrap-local (`http://localhost:3550`); ningún correo, SMS ni
  WhatsApp sale del entorno de desarrollo."
- Es un cambio de harness, así que va en su **propio PR chico, antes de este**, como pide `CLAUDE.md`. Todavía no
  existe: es el primer paso del plan.

### Consola

La columna "Owner" de la tabla muestra:

| Situación | Texto | Acciones |
|-----------|-------|----------|
| Sin owner y sin invitación | "Sin invitar" | Invitar owner |
| Invitación pendiente | "Invitación enviada", con nombre, correo y "vence el …" | Reenviar, Cancelar |
| Invitación vencida | "Vencida", con nombre y correo | Reenviar, Cancelar |
| Con owner | Nombre y correo, con *Activo* | Cambiar owner |
| Con owner y otra invitación pendiente | Las dos líneas | Reenviar, Cancelar |

- La lista carga owner e invitación con `with()`, sin N+1, y afirma su número de consultas.
- Cancelar pide confirmación en un `Dialog`. Invitar y reenviar no.

### Logs y bitácora

- Eventos `tenant_invitation.sent`, `tenant_invitation.resent`, `tenant_invitation.cancelled`,
  `tenant_invitation.accepted`, `tenant.owner_changed` y `tenant.trial_started`. Todos van también a la bitácora: afectan
  quién entra a un cliente.
- El sujeto es la invitación o la empresa. `metadata` lleva solo ids (`tenant_id`, `invitation_id`, `user_id`), **nunca
  el correo, el nombre ni el token**.
- `email` se agrega a las llaves de `RedactSensitiveData`, con su test. `token` ya está.
- En la bitácora, `tenant_id` va nulo en las acciones de la consola, igual que en #34. En `accepted`, `owner_changed` y
  `trial_started`, `tenant_id` es el de la empresa: ya son hechos de la empresa y su owner los podrá ver.
- `RecordAudit` toma `tenant_id` y `actor_id` del `Context`. Al aceptar, el `user_id` del request es nulo (cuenta
  nueva) o el de la persona que acepta, y todavía no hay `tenant_id`. Por eso la acción de aceptar registra esos tres
  eventos dentro de `Context::scope(…, ['user_id' => <quien acepta>, 'tenant_id' => <empresa>])`. El actor queda como
  quien acepta y `RecordAudit` no cambia. Agregar `tenant_id` al `Context` de cada request sigue en el PR de rutas por
  empresa.

### Pruebas

**Unit, con `mutates()`:**
- Estado de la invitación en los bordes: el instante exacto de `expires_at`, un segundo antes y uno después;
  cancelada; aceptada.
- La regla de "¿arranca la prueba?" para cada estado de la empresa.

**Feature:**
- El superadmin invita, reenvía y cancela. En cada caso se revisan las props, la base de datos, el correo enviado con
  `Notification::fake()`, el log y la bitácora.
- Invitar de nuevo cancela la pendiente.
- La validación rechaza un correo inválido, un nombre vacío y a la persona que ya es el owner.
- Un usuario sin rol y uno sin sesión reciben 404 o la redirección al login. Una invitación de otra empresa en la URL da 404.

**Feature, del lado del enlace:**
- Token válido → formulario.
- Inválido, usado, cancelado, vencido o reemplazado por un reenvío → "ya no es válido".
- Cuenta nueva: crea el usuario verificado, la membresía, `accepted_at` y `trial_started_at`; deja la sesión iniciada y
  redirige a `/admin`.
- Cuenta existente sin sesión: va al login y regresa.
- Cuenta existente con sesión: acepta sin tocar la contraseña.
- Sesión con otro correo, con o sin cuenta para el correo invitado: rechaza. "Cerrar sesión y continuar" regresa al enlace sin sesión.
- Aceptar dos veces: la segunda vez el enlace "ya no es válido".
- Empresa activa o desactivada: no arranca la prueba.
- Cambio de owner: el owner anterior pierde su membresía.
- Contraseña débil o sin confirmación: error, y no se crea nada.

**Feature, privacidad de logs:**
- Ninguna línea de log ni fila de bitácora contiene el correo, el nombre ni el token.

**Base de datos:**
- Insertar un segundo owner o una segunda invitación pendiente viola los índices únicos.

**Browser:**
- `visitPage` de la página del enlace (formulario, cuenta existente e inválido) y de la consola con la columna Owner,
  en claro, oscuro y móvil.
- Flujo completo: invitar desde la consola, abrir el enlace de la notificación falsa, cerrar la sesión del
  superadmin con "Cerrar sesión y continuar", aceptar y llegar a `/admin`.

### Commits (un comportamiento cada uno)

1. Membresía e invitaciones: migraciones, modelos y estado de la invitación.
2. Invitar al owner desde la consola (correo y columna Owner).
3. Aceptar con cuenta nueva (inicia la prueba).
4. Aceptar con cuenta existente.
5. Reenviar y cancelar.
6. Cambiar de owner.

## Lo que se deja al constructor

- Nombres internos de clases, acciones, DTOs y páginas Inertia, siguiendo la estructura de módulos de `CLAUDE.md`.
- El texto exacto del correo y de las páginas, dentro del tono del design system (el panel tutea).
- El valor exacto del `throttle` de las rutas públicas.
- La composición de componentes en `resources/js/components/tenancy/`, respetando los límites del linter.
