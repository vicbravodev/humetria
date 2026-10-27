# Landing de Humetría, formulario de demo y la app en `/admin`

Fecha: 2026-10-27. Estado: propuesta.

## Intención

`/` deja de ser la bienvenida del starter kit y pasa a ser la landing de Humetría. Su único objetivo es que el
visitante **agende una demo**: no hay registro abierto. Las empresas entran solo después de una demo, y también la prueba gratis se agenda.
La app (panel de RH y consultores) vive detrás de `/admin`.

## Decisiones del usuario

- **Públicos:** le habla a la vez a la empresa que aplica la NOM-035 ella misma y al consultor que la aplica a varios clientes.
- **Mensaje central:** aplicar la NOM-035 sin complicarse, con apego a las guías, la regulación y las buenas prácticas.
- **Llamado a la acción:** "Agenda una demo", con un formulario. Sin precios, sin testimonios ni logos de clientes por ahora.
- **Campos del formulario:** nombre, correo, WhatsApp, empresa, "soy empresa o consultor", rango de trabajadores y mensaje opcional.
- **Idioma:** solo español de México. La landing tutea, igual que el panel.
- **Rutas:** `/` es siempre la landing y la app va en `/admin`. Más adelante puede pasar a un subdominio.
- **Solicitudes de demo:** llegan a una consola que solo ve un correo de superadmin. Es un proyecto aparte, el siguiente.
- **Aviso de privacidad:** todavía no hay datos del responsable (razón social, domicilio, correo ARCO). La LFPDPPP lo exige antes
  de recabar datos personales en producción. Se construye el formulario, pero **no se publica en producción** hasta tener el aviso.

## Decisiones de Claude (revisables)

- **Todo lo de la app se mueve bajo `/admin`**, incluido el login de Fortify (`/admin/login`) y settings
  (`/admin/settings/...`). Así `/` y lo que cuelgue de ahí es solo marketing, y las cookies y la CSP del panel no se mezclan con
  contenido público. `/admin` sin sesión lleva al login.
- **El registro público (`/register`) se apaga en el mismo PR**, porque ya no hay autoservicio. Las cuentas las crea el
  superadmin en el proyecto de Tenancy.
- **Módulo nuevo `Demos`** (`app/Modules/Demos`) para las solicitudes de demo. No tiene `tenant_id`, porque es un dato
  de la plataforma y no de una empresa. Ningún otro módulo lo importa. La consola de superadmin lo va a leer a través de un contrato.
- **Protección anti-spam sin dependencias:** throttle por IP en la ruta pública y un campo trampa (honeypot) oculto. No hay
  CAPTCHA, porque sería una dependencia nueva y un tercero viendo datos.
- **Rangos de trabajadores** alineados con la NOM-035, para que sirvan después: 1–15, 16–50, 51–100, 101–500 y más de 500.

## Estructura de la landing

Sigue el design system (`docs/design-system/`) y la regla de "una cosa a la vez":

1. **Cabecera:** logo, un enlace "Iniciar sesión" (a `/admin`) y el botón "Agenda una demo" (lleva al formulario).
2. **Hero:** "Aplica la NOM-035 sin complicarte", un subtítulo con la promesa (confidencial, con apego a las guías oficiales,
   listo para la STPS) y el botón principal.
3. **Para quién:** dos tarjetas lado a lado, "Si la aplicas en tu empresa" y "Si eres consultor".
4. **Cómo funciona:** 4 pasos (cargas a tu gente, Humetría invita, cada trabajador contesta de forma confidencial,
   recibes el diagnóstico y el informe).
5. **Confidencialidad y apego a la norma:** cómo se protege el anonimato y qué se cumple (Guías I/II/III, tamaño de muestra,
   informe del numeral 7.7).
6. **Formulario "Agenda una demo".**
7. **Pie:** el sello de marca y el aviso de privacidad (marcado como pendiente) y los términos.

Los textos los propone Claude en el PR y el usuario los revisa.

## Fuera de alcance

- La consola de superadmin, que es el proyecto siguiente.
- Enviar correos o WhatsApp al recibir una solicitud.
- El texto del aviso de privacidad y de los términos, porque faltan los datos del responsable.
- SEO avanzado y analítica.

## PRs

1. **La app vive en `/admin`.** Mueve las rutas de la app y las de Fortify, apaga el registro y actualiza los tests y la
   lista de rutas públicas.
2. **La landing en `/`.** Reemplaza `welcome.tsx`, lo saca de las dos listas de excepciones (`PENDING_REFACTOR` y
   `DesignTokensOnlyTest`) y le agrega su test `visitPage()`.
3. **Formulario de demo.** Módulo `Demos`: migración, modelo, acción, FormRequest, throttle, honeypot, log
   `demo_request.submitted` y ruta pública con su razón.

## Cómo se prueba

- **PR 1:** feature tests de que las rutas viejas ya no existen, de que `/admin` sin sesión redirige a `/admin/login` y de que
  `/register` no existe. Además, `RouteSecurityTest` actualizado y los tests de navegador apuntando a las rutas nuevas.
- **PR 2:** `visitPage('landing', '/')` en claro y oscuro, escritorio y móvil (axe, CSP y sin inglés), más un test de que
  los botones llevan al formulario.
- **PR 3:**
  - feature tests de validación con cada campo y sus bordes, de que se guarda, del log, del honeypot y del throttle;
  - un test de arquitectura del módulo;
  - un test de navegador que llena el formulario.
