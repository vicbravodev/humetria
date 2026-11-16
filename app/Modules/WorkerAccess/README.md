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

Una página de la persona (`/me/{slug}/...`) lleva `ResolveWorkerTenant` y `RequireWorkerSession` (no `auth:worker`, que haría
a la persona el usuario de la petición). Sin sesión, la página lleva a la entrada de esa empresa y guarda el destino
(`Support/WorkerDestination`: solo una página `/me/{slug}/...` de la misma empresa en este sitio, en su propia llave de
sesión, nunca en `url.intended` del panel); al entrar con llave, con un enlace pedido o tras crear la llave de un enlace de RH,
la persona llega ahí una vez. `WorkerSession` se lee una vez por petición (`Support/RequestWorkerSession`).

## Prueba reciente

Lo delicado pide que la persona haya probado que es ella hace menos de 5 minutos (`proof_at` de su sesión,
`worker_access.proof.minutes`). Cada módulo lo pide en su archivo de rutas con el alias **`worker.proof`**
(`Http/Middleware/RequireRecentProof`), después de `RequireWorkerSession` (`RouteSecurityTest` lo exige). Sin prueba reciente
el servidor no entrega el contenido ni acepta el envío:

- Una página (`GET`) lleva a `worker.proof.show` (`/me/{slug}/proof`) y, ya probada, regresa a ella (`WorkerDestination`).
- Un envío regresa a la página de donde salió (solo una de `/me/{slug}/...` de esa empresa; si no, al inicio) con el flash
  `proofRequired` (el paso de prueba). `components/worker-access/recent-proof-dialog.tsx`, montado una vez en `app.tsx`,
  lo abre y, ya probada, reenvía el último envío de Inertia de la página (mismos datos).
- JSON: 403 con `proofRequired: true` (401 sin sesión).

Cómo prueba la persona (`Support/ProofSteps`): con al menos una llave activa, firma con ella (reto de contexto
`proof:{sesión}`; una llave ajena, revocada o de otra empresa da la misma respuesta) y **no se le ofrece código**. Sin llave,
pide un código de seis dígitos por correo (`SendProofCode`, `Notifications/ProofCode`, inmediato, nunca en cola): en caché
solo su HMAC ligado a la persona y a la sesión, 10 minutos, 5 intentos (el sexto se rechaza aunque sea correcto), uno nuevo
anula el anterior y nunca es igual; 3 envíos por persona y por sesión cada 10 minutos (`Support/ProofCodes`). Sin llave ni
correo no puede probar: que pida su acceso a Recursos Humanos. Entrar con llave ya es una prueba; entrar con un enlace no.
Logs: `worker_access.proof_given` (`via`: `key` con `key_id`, o `code`), `worker_access.proof_refused`,
`worker_access.proof_code_sent`; nunca el código ni el correo. Sin bitácora.

Rutas delicadas: `portal.support.show` (el mensaje de apoyo de la NOM-035); `portal.policy.acknowledge`;
`portal.seating.acknowledgments.store`; `portal.vacations.requests.store`, `portal.vacations.statements.acknowledge`,
`portal.vacations.team.approve`, `portal.vacations.team.reject`; `portal.documents.acknowledge`, `portal.equipment.respond`,
`portal.visits.confirm`.

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
- `WorkerSection::for(WorkerIdentity): ?WorkerSectionLink`: lo que cada herramienta ofrece en el inicio (`/me/{slug}`). Cada
  módulo etiqueta la suya `worker.sections`; `Support/WorkerSections` las junta en un orden fijo y una que falla se reporta
  y se omite sin tumbar el inicio.
- `WorkerSignatures` y `WorkerCredentials`: firmar y leer llaves (los usa Attendance). `WorkerKeys`: crear una llave.
