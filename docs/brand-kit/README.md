# Brand kit de redes

Imágenes en `png/` (se regeneran con `node docs/brand-kit/build.mjs`, requiere `npm run build` por las fuentes).
Copys, bios, Reels y calendario: [`copys.md`](copys.md). Colores, voz y logo: `docs/design-system/`.

| Archivo | Medida | Dónde |
|---|---|---|
| `perfil-jade.png` (principal), `perfil-claro.png` | 1080×1080 | Foto de perfil de las 3 redes (el símbolo queda dentro del recorte circular) |
| `portada-linkedin.png` | 1584×396 | Portada de LinkedIn (el logo de perfil tapa la izquierda: el contenido va a la derecha) |
| `portada-facebook.png` | 1640×624 | Portada de Facebook (contenido centrado, zona segura en móvil) |
| `c1…c4`, `01…07` | 1080×1350 (4:5) | Publicaciones de Instagram, LinkedIn y Facebook |
| `c2-carrusel-*`, `02-carrusel-*` | 1080×1350 | Carruseles de 5 láminas |
| `reel-*-portada.png` | 1080×1920 | Portadas de Reels |

Reglas: un solo acento jade, Figtree 400–600, sin emoji ni exclamaciones, logo en minúsculas, texto ≥ 4.5:1.

## Enlaces en la landing

El pie de la landing muestra el ícono de cada red cuya variable esté puesta (solo `https://`); sin variable, no hay enlace.
Cuando existan las cuentas, en Dokploy: `SOCIAL_LINKEDIN_URL`, `SOCIAL_FACEBOOK_URL`, `SOCIAL_INSTAGRAM_URL`.
