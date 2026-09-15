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
- `.env` files are **gitignored everywhere** (root, `backend/`, `frontend/`). Templates live in each dir's `.env.example`. `SYMFONY` requires an actual `backend/.env` to boot — first-time setup must copy `backend/.env.example` → `backend/.env`; deploy.sh and the docker image create it automatically if missing. Keep real secrets (DB creds, `OPENROUTER_API_KEY`) in the gitignored `backend/.env.local`.

## Commands (dev, in order)

```bash
# 1. MySQL (root docker-compose.yml): port 3307, db job_track, user/pass app/app
docker compose up -d db
# 2. Backend
cd backend
copy .env.example .env                      # required once; .env is gitignored (Windows). On Unix: cp .env.example .env
composer install && php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public          # Symfony dev server
# 3. Frontend
cd frontend && npm install
npm run build                            # runs tsc --noEmit, then writes ../backend/public/build
npm run check                            # format:check → lint → test → build
npm run dev                              # Vite on 5173, /api proxied to 8000
```

- `backend/compose.yaml` + `compose.override.yaml` are unused Symfony-generated Postgres stubs. Ignore them; the real DB compose file is the repo-root `docker-compose.yml`.

## QA

No CI. Backend checks run via Composer scripts: `composer check` runs **phpcs → phpunit → phpstan** in that order; each also runs standalone (`composer phpcs`, `composer phpunit`, `composer phpstan`).

Frontend checks run via npm scripts: `npm run check` runs **prettier → eslint → vitest → build** (build runs `tsc --noEmit` + `vite build`). Standalone: `npm run lint` (ESLint 9 flat config, `eslint.config.js`), `npm run format` / `npm run format:check` (Prettier), `npm run test` / `npm run test:watch` (Vitest). Vitest uses jsdom + Testing Library; config lives in `vite.config.ts` (`test` block), setup in `src/test/setup.ts`, tests co-located as `*.test.ts(x)`.

- ESLint pulls in `react-hooks` v7 recommended-latest rules. `react-hooks/set-state-in-effect` is deliberately downgraded to `warn` because it flags the codebase's canonical async fetch-in-effect pattern.
- Backend tools: PHPUnit 11 (`phpunit.dist.xml`, tests in `backend/tests/`, service/entity unit tests), PHPStan level 6 + Symfony/Doctrine/PHPUnit extensions (`phpstan.neon`), PHPCS PSR-12 (`phpcs.xml.dist`, only `src/` + `tests/`; long-line sniff disabled; `tests/bootstrap.php` excluded).
- Verify PHP changes by running `composer check`, then the Symfony server + endpoint hit. Verify frontend changes by running `npm run check`.

## Auth / API contract (do not break)

- Session-based auth via SameSite=Lax cookie (`credentials: 'include'` from the SPA).
- All non-GET/HEAD/OPTIONS `/api` requests **except `POST /api/auth/login` must send the CSRF token in the `X-CSRF-TOKEN` header** — enforced by `backend/src/EventSubscriber/ApiCsrfSubscriber.php`. Tokens are issued by `/api/setup/status`, `/api/auth/login`, and `/api/auth/me`.
- `frontend/src/api.ts` handles cookie + CSRF header automatically (module-level token, set via `setCsrfToken`). New frontend code should use that `api` client; new backend endpoints should assume any non-GET request has a valid CSRF token.
- `security.yaml` enforces full auth on all `/api` routes except `setup` and `auth/login`. First-run setup creates the admin via the Setup page or `php bin/console app:create-admin <email> <name> <password>` in Docker (ADMIN_EMAIL/ADMIN_PASSWORD env).
- Other console command: `app:reset-password <email> <password>`.

## AI-assisted data entry ("Auto-fill from message")

- Paste an email/LinkedIn message → `POST /api/ai/extract` (backend `src/Controller/AiController.php`) sends it to OpenRouter (`backend/src/Service/AiExtractor.php`, `symfony/http-client`) and returns a normalized `{job, interview, summary}` object. The **frontend** (`frontend/src/components/AiImportModal.tsx`, helpers in `frontend/src/aiImport.ts`) then lets the user review/edit and saves **through the existing create endpoints** (job, application, interview) — no separate backend persist logic.
- Config lives in `backend/.env(.local)`: `OPENROUTER_API_KEY` (empty = feature disabled; returns 503 on extract) and `OPENROUTER_MODEL` (defaults to `google/gemma-4-31b-it:free`). Both are resolved in `backend/config/services.yaml`.
- `AiExtractor` normalizes/validates model output (allowed job statuses, URLs, dates → `Y-m-d\TH:i:s`, interviewer lists) and throws `AiExtractionException` (config problems → 503, upstream/call errors → 502).

## Deployment

- `deploy.sh` (server): `git pull` + `composer install --no-dev` + optional frontend build + migrations + cache warm.
- `push-assets.ps1` / `push-assets.sh` (local): build frontend and SCP `backend/public/build/` to the server. Connection settings come from the **root `.env`** as `REMOTE_USER`, `REMOTE_HOST`, `REMOTE_PATH`, `REMOTE_PORT` (see `.env.example` — its "copy to .push-assets.env" comment is stale; the script reads `.env`).
- Production runs Apache + php:8.3; docker-compose `web` service runs migrations and cache warmup on boot.