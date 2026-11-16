# Entorno local

Lo esencial está en `CLAUDE.md` («Entorno local»); aquí va el detalle.

## Primera vez

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
sail artisan key:generate
sail artisan migrate
sail artisan humetria:create-superadmin   # el primer superadmin
sail artisan geoip:update                 # la base de ciudades de los correos de cuenta; luego la baja el scheduler cada mes
```

## Qué levanta Sail

- `sail up -d` levanta la app en `http://localhost` (puerto 80), Postgres, Valkey, Vite, Horizon, el scheduler y
  mailtrap-local. Docker reinicia los que se caen (`restart: unless-stopped`).
- Vite arranca solo; su `node_modules` de Linux vive en un volumen, aparte del de tu máquina.
- Las colas van en Redis (Valkey) y las procesa el contenedor `horizon`. Tras cambiar el código de un job:
  `sail artisan horizon:terminate`. El tablero vive en `/horizon`, solo para el superadmin. No se corre `queue:work` a mano.
- El correo local va a mailtrap-local (bandeja en `http://localhost:3550`): ningún correo sale del entorno de desarrollo.

## Tests

Corren en local, no en el contenedor, contra el Postgres de Docker en el puerto 5433 (`FORWARD_DB_PORT`); `phpunit.xml` fija
ese host y puerto, así que el `.env` puede apuntar a `pgsql`. Los de navegador requieren `npm run build`.

## Worktrees en paralelo

- Cada checkout prueba contra su propia base: `testing` en el principal y `testing_<directorio>_<hash>` en un worktree, que
  `tests/bootstrap.php` crea sola (un `DB_DATABASE` explícito en el shell gana). No deadlockean entre sí.
- Un worktree nuevo se prepara con `composer worktree:setup` (`.env`, dependencias y build).
- `composer worktree:prune` lista las bases de worktrees borrados y `composer worktree:prune -- --drop` las elimina
  (revisa la lista antes: otra sesión podría usarlas).
- La app de Sail en `http://localhost` sirve el checkout principal, no el worktree.

## Hooks de Claude (`.claude/hooks/`)

- Al editar: Pint + PHPStan en cada PHP; `vp fmt` + `vp lint` en cada TS/TSX (en el checkout dueño del archivo).
- Al terminar un turno (Stop): solo checks estáticos sobre lo cambiado: `pint --dirty --test` y PHPStan en los PHP cambiados de
  `app`, `config`, `database`, `routes` y `bootstrap/app.php`; `npm run check` + `npm run types:check` si cambió el frontend.
  No corre tests.
