# Blog: cómo se escribe y se publica

Los artículos son archivos Markdown en `content/blog/{slug}.md`; el nombre del archivo es la dirección
(`/blog/{slug}`). No hay base de datos ni panel: el módulo `Blog` los lee del disco.
Qué escribir y en qué orden: [`plan-editorial.md`](plan-editorial.md).

## Front matter

```yaml
---
title: "Jornada de 40 horas: el calendario 2026-2030"   # 60 caracteres o menos
description: "…"                                        # de 120 a 160 caracteres
tool: attendance                                        # de qué herramienta habla: nom_035, nom_037, ley_silla, attendance, complaints, engagement, leave, org_chart o compensation
published_at: 2026-11-18                                # no sale antes de este día
updated_at: 2026-11-18                                  # opcional; sale como «Actualizado» y como dateModified
reviewed_by: "Equipo Humetría"                          # quién lo revisó
reviewed_at: 2026-11-18                                 # null = borrador, no se publica
sources:                                                # al menos una, https y de gob.mx
  - https://www.diputados.gob.mx/LeyesBiblio/ref/lft/LFT_ref52_01may26.pdf
---
```

`tool` decide el color de la familia del artículo (el de la herramienta en el resto del sitio), el enlace a su página y el
texto de la portada. La lista vive en `app/Modules/Blog/Support/BlogTools.php` y repite las familias de `landing-families.ts`.

## Portadas

Cada artículo tiene dos imágenes de 1200×630 en `public/blog/`: `{slug}.webp` (la de la página y las tarjetas) y
`{slug}-og.jpg` (con el título, para Open Graph y el JSON-LD; un JPEG porque no todos los rastreadores leen WebP).
Se generan con Remotion y los tokens de marca, desde `video/` (necesita `cwebp`: `brew install webp`):

```bash
cd video && npm ci && npm run covers:blog            # todas
cd video && npm run covers:blog -- {slug}            # una
```

La composición es `video/src/presentations/blog-cover`. Un artículo sin sus dos portadas rompe el CI
(`PublishedContentTest`). Se vuelven a generar al cambiar el título o la herramienta de un artículo.

## El visto bueno

**Un artículo se publica cuando `reviewed_at` tiene fecha, `published_at` ya llegó y el PR se mezcla.** Quien redacta
(persona o agente) deja `reviewed_by: null` y `reviewed_at: null`; solo el dueño las llena después de leerlo contra
la fuente. Un borrador pasa todas las reglas y simplemente no aparece.

## Reglas que el CI exige

`tests/Unit/Blog/PublishedContentTest.php` corre `ArticleParser` sobre cada archivo. Un artículo rompe el CI si:
no tiene los campos de arriba o están fuera de límite; la fuente no es `https` de `gob.mx`; el cuerpo tiene un `# `
(el título ya es el h1) o HTML crudo; tiene menos de 500 palabras; no enlaza a una página de Humetría (por ejemplo
`/#agenda-una-demo`); el slug no es minúsculas con guiones, o el título se repite.

## Para el SEO

El artículo sale como HTML del servidor (sin React ni Node), con título, descripción, canonical, Open Graph y JSON-LD
`Article`. Entra solo al sitemap `/blog/sitemap.xml` (declarado en `robots.txt`); `/blog` está en `public/sitemap.xml`.
Un artículo con la jornada o una norma cambiante se revisa cuando cambie la fuente y se sube `updated_at`.

La landing y las páginas de cada herramienta enlazan al blog desde el navbar (`header-blog`) y desde el pie (`footer-blog`), los dos un `<a href="/blog">` plano, que es lo que reparte el peso de todo el sitio hacia él.

El header del blog (`resources/views/partials/public-header.blade.php`) es el de la landing: los dos leen sus enlaces de
`resources/js/lib/public-nav.json` (`App\Http\PublicNav` en PHP), así que agregar un enlace se hace en ese solo archivo.
El blog sigue siendo HTML del servidor: «Herramientas» es un enlace a su sección y el menú del teléfono es un `<details>`. Los artículos enlazan a la demo (`/#agenda-una-demo`).
