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

Cada artículo tiene dos imágenes de 1200×630 en `public/images/blog/`: `{slug}.webp` (la de la página y las tarjetas) y
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

## Diagramas

Donde un diagrama explica mejor que el texto, se pide en su propio párrafo con `[diagrama: nombre]`; la página lo dibuja ahí, con
el color de la familia de la herramienta del artículo y con su texto en el HTML del servidor (listas, no imágenes). Cada nombre es
el archivo de un componente en `resources/js/components/blog/diagrams/` (`nom035-paso-a-paso.tsx`) y se registra en su `index.ts`;
un artículo que pide un diagrama sin componente rompe el CI. Hoy hay: `nom035-guias-por-centro`, `nom035-paso-a-paso`,
`jornada-escalera`, `topes-horas-extra`, `horas-extra-semana`, `registro-diez-pasos` y `vacaciones-por-antiguedad`. Un diagrama
solo dice lo que dice el artículo (cada uno cita su fuente al pie): si cambia la norma, se cambian los dos.

## Cómo se ve y se renderiza

El blog son dos páginas de Inertia (`resources/js/pages/blog/index.tsx` y `show.tsx`) con el header y el pie de la landing, y
pasan por Node (`App\Http\SsrPages`): el texto del artículo sale en el HTML del servidor, con título, descripción, canonical,
Open Graph y JSON-LD `Article` (con `datePublished` y `dateModified`). El cuerpo es el HTML que `ArticleParser` saca del
Markdown; la página lo pinta tal cual. Entra solo al sitemap `/blog/sitemap.xml` (declarado en `robots.txt`); `/blog` está en
`public/sitemap.xml`. Un artículo con la jornada o una norma cambiante se revisa cuando cambie la fuente y se sube `updated_at`.

Si Node se cae, la página responde 200 con las etiquetas del `<head>` y React la pinta en el navegador; el cuerpo solo falta
para un rastreador que no ejecuta JavaScript. Lo vigila `tests/Ssr/BlogSsrTest.php` (`composer test:ssr`).

La landing y las páginas de cada herramienta enlazan al blog desde el navbar (el menú «Recursos», `header-blog`) y desde el pie
(`footer-blog`), los dos un `<a href="/blog">` plano, que es lo que reparte el peso de todo el sitio hacia él. Los artículos
enlazan a la demo (`/#agenda-una-demo`) y a la página de su herramienta.
