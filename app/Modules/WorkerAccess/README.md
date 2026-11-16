# WorkerAccess (Acceso de la persona trabajadora)

La identidad de la persona que trabaja, que no tiene cuenta: su llave de acceso (WebAuthn), sus enlaces de un solo uso y
su sesión. Diseño: `docs/superpowers/specs/2026-11-14-worker-access-design.md`. Existe para toda empresa no bloqueada; no
es una herramienta del catálogo.

## Tablas

- `worker_credentials`: las llaves. `credential_id` y `public_key` nunca llegan a una página, un log ni la bitácora.
  `registered_via` (`issued_link`, `session`) y `revoked_reason` (`reissued`, `revoked_by_hr`, `not_me`).
- `worker_access_links`: enlaces de un solo uso. Solo el hash del token; `issued` (RH, 48 h, permite crear una llave) o
  `requested` (la persona, 30 min, solo abre sesión). Un UPDATE condicional lo gasta una vez.
- `worker_access_states`: una fila por persona con `valid_after`; toda sesión abierta antes deja de valer.

## Sesión

Una sesión, dos entradas: la llave o un enlace. Vive en la entrada `worker` de la sesión de Laravel; solo
`Support/WorkerSessionStore` la toca (guard `worker` en `config/auth.php`). Vale si es de la empresa del slug, se usó en
los últimos 30 min, se abrió hace menos de 8 h, es posterior a `valid_after`, la persona sigue activa y la empresa no está
bloqueada; si no, se borra. Entrar y salir regeneran el id; «Salir» borra solo esa entrada. Valores en
`config/worker_access.php`.

## Reglas

- Crear una llave: solo con una sesión que entró con un enlace `issued` o con otra llave, hasta 3 activas. Un enlace
  `requested` nunca. Cada llave nueva avisa a la persona con «No fui yo» (dirección firmada), que la anula, mueve
  `valid_after`, anula sus enlaces y avisa al contacto de la empresa.
- Pedir un enlace, fallar una firma y abrir un enlace que no sirve responden igual exista o no la persona. La pantalla
  de entrada no muestra nombres.
- Ni el token, ni su hash, ni el correo escrito llegan a logs, bitácora o props (`tests/Feature/WorkerAccess/PrivacyTest.php`).

## Panel: «Accesos»

`/admin/{tenant}/access` (`tenant.worker-access.*`, `routes/worker-access-panel.php`), para toda empresa con o sin
herramientas; un sindicato recibe 404. `view-worker-access` (owner, hr, viewer) lo ve; `manage-worker-access` (owner, hr)
actúa. Por persona: con llave, invitada (un enlace `issued` que aún abre) o sin invitar; sus llaves, respaldo y último uso.
Acciones: invitar (`IssueAccessLink`), invitar a un centro (`InviteCenter`, un job `SendWorkerInvitation` por persona sin
llave ni invitación vigente; las sin correo se cuentan), reemitir (`ReissueAccess`, CUJ 7: en una transacción revoca todas
las llaves con `reissued`, mueve `valid_after`, anula sus enlaces y emite uno nuevo) y revocar una llave (`RevokeCredential`,
`revoked_by_hr`: deja de verificar; no cierra sesiones, para eso está reemitir). Para una persona sin correo el enlace se
muestra una vez en el flash `accessLink`; la bitácora (`worker_access.link_issued`, `delivered_by_rh`) guarda quién lo mostró.

## Contratos

- `WorkerSession::current(): ?WorkerIdentity`: la persona de la sesión de esta petición.
- `WorkerLinks`: `home()` y `to($route, $parameters)`, direcciones de la persona sin credencial.
- `WorkerInvitations::send($employeeId, $issuedBy): InvitationOutcome`: RH emite el enlace (`sent`, `without_email` con
  el enlace para mostrarlo una vez, `unavailable`).
- `WorkerSignatures`, `WorkerKeys`, `WorkerCredentials`: firmar, crear y leer llaves (los usa Attendance).
