# Sso (Inicio de sesión único)

Las personas con cuenta en `/admin` (dueño, RH, consulta…) entran con el proveedor de identidad de su empresa por OpenID
Connect: Microsoft Entra, Google Workspace u Okta. Es una herramienta del catálogo (`sso`, solo el plan **Enterprise**; un
trial la trae). No es para la persona trabajadora sin cuenta (WorkerAccess) ni usa SAML. Sin intermediarios de pago: la
empresa paga su propio proveedor y nosotros solo hablamos OIDC.

## Tablas (`tenant_id`, sin scope por empresa: la ruta pública lee antes de resolver empresa; cada consulta nombra la suya)

- `sso_connections`: una por empresa. `issuer`, `client_id`, `client_secret` (cifrado con `APP_KEY`, nunca a página, log ni
  bitácora), `enabled`, `enforced` (solo con `enabled`: `check` en la base).
- `sso_domains`: dominios de correo que la empresa dice suyos y su token. Solo uno **verificado** por dominio en toda la
  plataforma (índice único parcial): quien configura un proveedor propio no puede entrar a cuentas de otra empresa.

## Verificación de dominio (obligatoria)

Cada dominio pide un registro TXT en `_humetria-sso.{dominio}` con valor `humetria-sso={token}`. El dueño publica el registro y
pulsa «Verificar ahora» (`Contracts/DomainOwnership`, con DNS real en `Support/DnsDomainOwnership`). Sin un dominio verificado no
se puede activar SSO; si se quita el último, se apaga. Cambiar de proveedor no quita la verificación de un dominio que sigue.

## El flujo (rutas públicas `admin/sso/*`, con throttle por IP)

1. `GET admin/sso/{slug}` (o `POST admin/sso/discover` desde el login, por el dominio del correo): discovery del `issuer`
   configurado; manda al proveedor con `state`, `nonce` y PKCE (Socialite guarda `state` y el verificador en la sesión). Empresa
   inexistente, sin el módulo, apagada, suspendida o sin dominio verificado: el mismo 404.
2. `GET admin/sso/callback`: cambia el código por tokens (cliente HTTP de Laravel; secreto en el cuerpo) y valida el ID token
   (`Support/IdToken`): `iss`, `aud`, `exp`, `nonce`. La firma no se revisa: el token llega directo del endpoint de tokens por TLS
   a cambio de nuestro código y secreto (OIDC Core §3.1.3.7). Si algún día se usa otro flujo, agregar JWKS.
3. `Actions/CompleteSsoSignIn`: correo **verificado por el proveedor** (`email_verified`, o `xms_edov` de Entra), de un dominio
   **verificado de esa empresa**, de alguien que **ya es miembro**. Nunca crea cuentas y nunca deja entrar al personal de Humetría.
   Todo fallo es la misma pantalla («No pudimos iniciar tu sesión con SSO…»); el motivo solo va al log (`sso.sign_in_failed`,
   `reason`), sin correo.
4. Entra sin pasar por la contraseña, el segundo factor ni las llaves: el proveedor es quien autentica. La sesión recuerda las
   empresas por las que esa persona entró (`sso.user_id`, `sso.tenants`).

## Exigir SSO

Con `enforced`, `Http/Middleware/RequireSsoWhenEnforced` (después de `ResolveTenant`) manda a toda persona sin esa marca al
proveedor, aunque haya entrado con contraseña, 2FA o llave: la cuenta abre, **esa empresa** no (una consultora con varias
empresas sigue entrando a las demás). Solo cuenta mientras el plan tenga el módulo.

**Salida de emergencia:** el **dueño** nunca se ve obligado (`manage-members`): si el proveedor falla, entra con su contraseña
y 2FA y desactiva el SSO desde la misma pantalla. Si el dueño tampoco puede, soporte restablece su acceso desde la consola
(restablecer contraseña / quitar 2FA) y apaga SSO con una sesión de impersonación… que no sirve para la pantalla de SSO
(`ForbidWhileImpersonating`): en ese caso, ingeniería: `sso_connections` de esa empresa → `enabled = false, enforced = false`.

## El personal de Humetría

Aparte del SSO por empresa (que rechaza al personal), la plataforma tiene **un** proveedor propio para su gente (superadmin,
soporte): `config/sso.php` → `staff`, desde `SSO_STAFF_ISSUER`, `SSO_STAFF_CLIENT_ID`, `SSO_STAFF_CLIENT_SECRET` y
`SSO_STAFF_DOMAIN` (con Google Workspace: `https://accounts.google.com`; redirección `{APP_URL}/admin/sso-staff/callback`).
Se entra por `GET /admin/sso-staff`, o escribiendo el correo del personal en «Continuar con SSO» del login (`POST admin/sso/discover` lo manda ahí cuando el dominio es `SSO_STAFF_DOMAIN`). Mismo flujo (state, nonce, PKCE, ID token), y
`Actions/CompleteStaffSsoSignIn` exige correo verificado del dominio del personal, de una cuenta que ya existe y tiene rol de
plataforma; nunca crea cuentas. Sin las cuatro variables la ruta responde 404. Contraseña, 2FA y llave siguen funcionando.
Bitácora y log: `sso.staff_signed_in`; fallos: `sso.staff_sign_in_failed` con `reason`.

## Bitácora y logs

`sso.configured`, `sso.domain_verified`, `sso.enabled`, `sso.disabled`, `sso.enforced`, `sso.enforcement_lifted` y `sso.signed_in`
van a la bitácora (`RecordAudit`) y al log, sin secretos ni correos. `client_secret`, `id_token`, `access_token` y
`code_verifier` están en `RedactSensitiveData`.

## Alta en el proveedor (lo que se le dice a la empresa)

Crear una aplicación web **OpenID Connect** con _authorization code_ y redirección `https://{app}/admin/sso/callback` (la
pantalla la muestra), alcances `openid email`, y copiar `issuer`, `client id` y `client secret`.

- **Entra:** _App registrations_; `issuer` = `https://login.microsoftonline.com/{tenant-id}/v2.0`. Entra no manda
  `email_verified`: se acepta `xms_edov`; agregar el reclamo opcional de correo.
- **Google Workspace:** `issuer` = `https://accounts.google.com`.
- **Okta:** `issuer` = el servidor de autorización (`https://{org}.okta.com` o `/oauth2/default`).
