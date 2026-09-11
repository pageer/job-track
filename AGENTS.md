# AGENTS.md

Job application tracker. Two apps: Symfony 7.4 API backend (`backend/`) + React 18 / Vite / TypeScript SPA (`frontend/`). The SPA is **served by Symfony**, not deployed as a separate host.

## Architecture facts that are not obvious

- Vite's `outDir` is `../backend/public/build` with `base: '/build/'` (frontend/vite.config.ts). The frontend build is a build artifact of the backend app: `backend/public/build/` is gitignored. Do not hand-edit files there.
- All non-`/api` GET routes fall through to `SpaController` (backend/src/Controller/SpaController.php), which serves that `index.html`. Use the Vite dev server (port 5173) for frontend work; it proxies `/api` to `127.0.0.1:8000`.
- Symfony API responses use serializer groups (`*.read`, `*.detail`, `job.list`). A new field/entity shows nothing in JSON unless it has a matching group annotation on the entity.
- Every entity is scoped to the owning user: controllers fetch via repo `findByUser()` or a private `findOwnedX()` helper (checks `$user->getId()`). Follow this pattern — there is no global authz layer.
- Controllers/commands persist via an injected `EntityManagerInterface`; do NOT call `$repo->getEntityManager()` from outside a repository (protected on `EntityRepository` in ORM 3 — PHPStan enforces this).
- Root `package-lock.json` is an empty stub and gitignored. Real lockfiles: `frontend/package-lock.json`, `backend/composer.lock`.
- Root `tags` is ctags output (gitignored).

## Commands (dev, in order)

```bash
# 1. MySQL (root docker-compose.yml): port 3307, db job_track, user/pass app/app
docker compose up -d db
# 2. Backend
cd backend && composer install && php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public          # Symfony dev server
# 3. Frontend
cd frontend && npm install
npm run build                            # runs tsc --noEmit, then writes ../backend/public/build
npm run dev                              # Vite on 5173, /api proxied to 8000
```

- `backend/compose.yaml` + `compose.override.yaml` are unused Symfony-generated Postgres stubs. Ignore them; the real DB compose file is the repo-root `docker-compose.yml`.

## QA (backend only)

- No CI and no frontend linter. Frontend typecheck gate is `npm run build` (runs `tsc --noEmit`).
- Backend checks run via Composer scripts: `composer check` runs **phpcs → phpunit → phpstan** in that order; each also runs standalone (`composer phpcs`, `composer phpunit`, `composer phpstan`).
- Tools: PHPUnit 11 (`phpunit.dist.xml`, tests in `backend/tests/`, service/entity unit tests), PHPStan level 6 + Symfony/Doctrine/PHPUnit extensions (`phpstan.neon`), PHPCS PSR-12 (`phpcs.xml.dist`, only `src/` + `tests/`; long-line sniff disabled; `tests/bootstrap.php` excluded).
- Verify PHP changes by running `composer check`, then the Symfony server + endpoint hit.

## Auth / API contract (do not break)

- Session-based auth via SameSite=Lax cookie (`credentials: 'include'` from the SPA).
- All non-GET/HEAD/OPTIONS `/api` requests **except `POST /api/auth/login` must send the CSRF token in the `X-CSRF-TOKEN` header** — enforced by `backend/src/EventSubscriber/ApiCsrfSubscriber.php`. Tokens are issued by `/api/setup/status`, `/api/auth/login`, and `/api/auth/me`.
- `frontend/src/api.ts` handles cookie + CSRF header automatically (module-level token, set via `setCsrfToken`). New frontend code should use that `api` client; new backend endpoints should assume any non-GET request has a valid CSRF token.
- `security.yaml` enforces full auth on all `/api` routes except `setup` and `auth/login`. First-run setup creates the admin via the Setup page or `php bin/console app:create-admin <email> <name> <password>` in Docker (ADMIN_EMAIL/ADMIN_PASSWORD env).
- Other console command: `app:reset-password <email> <password>`.

## Deployment

- `deploy.sh` (server): `git pull` + `composer install --no-dev` + optional frontend build + migrations + cache warm.
- `push-assets.ps1` / `push-assets.sh` (local): build frontend and SCP `backend/public/build/` to the server. Connection settings come from the **root `.env`** as `REMOTE_USER`, `REMOTE_HOST`, `REMOTE_PATH`, `REMOTE_PORT` (see `.env.example` — its "copy to .push-assets.env" comment is stale; the script reads `.env`).
- Production runs Apache + php:8.3; docker-compose `web` service runs migrations and cache warmup on boot.