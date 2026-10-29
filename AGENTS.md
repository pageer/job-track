# AGENTS.md

Job application tracker. Two apps: Symfony 7.4 API backend (`backend/`) + React 18 / Vite / TypeScript SPA (`frontend/`). The SPA is **served by Symfony**, not deployed as a separate host.

## Git & review workflow (always follow)

- **Never commit to Git without first asking the user.** This applies to commits, amends, and pushes — always confirm before staging or recording anything.
- After completing a coding task, run the `/review-response` skill and prompt the user to review the changes.
- After responding to the review, ask the user whether they want to re-review or whether you should commit the changes.

## Architecture facts that are not obvious

- Vite's `outDir` is `../backend/public/build` with `base: '/build/'` (frontend/vite.config.ts). The frontend build is a build artifact of the backend app: `backend/public/build/` is gitignored. Do not hand-edit files there.
- All non-`/api` GET routes fall through to `SpaController` (backend/src/Controller/SpaController.php), which serves that `index.html`. Use the Vite dev server (port 5173) for frontend work; it proxies `/api` to `127.0.0.1:8000`.
- Symfony API responses use serializer groups (`*.read`, `*.detail`, `job.list`). A new field/entity shows nothing in JSON unless it has a matching group annotation on the entity.
- Every entity is scoped to the owning user: controllers fetch via repo `findByUser()` or a private `findOwnedX()` helper (checks `$user->getId()`). Follow this pattern — there is no global authz layer.
- Controllers/commands persist via an injected `EntityManagerInterface`; do NOT call `$repo->getEntityManager()` from outside a repository (protected on `EntityRepository` in ORM 3 — PHPStan enforces this).
- Root `package-lock.json` is an empty stub and gitignored. Real lockfiles: `frontend/package-lock.json`, `backend/composer.lock`.
- Root `tags` is ctags output (gitignored).
- `.env` files are **gitignored everywhere** (root, `backend/`, `frontend/`). Templates live in each dir's `.env.example`. `SYMFONY` requires an actual `backend/.env` to boot — first-time setup must copy `backend/.env.example` → `backend/.env`; deploy.sh and the docker image create it automatically if missing. Keep real secrets (DB creds, `OPENROUTER_API_KEY`) in the gitignored `backend/.env.local`.
- A job search is **active** iff its `endDate` is `null` (there is no `status`/`isActive` column). `activeJobSearch()` in `frontend/src/jobSearches.tsx` returns the active search only when there is exactly one.
- The first main-nav link is computed by `jobsNavTarget()`: with exactly one active search it reads **Jobs** and points at `/searches/{id}`, otherwise **Job Searches** pointing at `/`. The list itself is held by `JobSearchesProvider` (mounted in `App.tsx`, wraps `Layout`) — call its `refresh()` after creating, editing, or deleting a search or the nav goes stale. `SearchDetailPage` keeps the "← All job searches" back-link, and the `Job Track` brand link also goes to `/`.

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

## OneDrive resume picker

- "Browse OneDrive" opens `frontend/src/components/OneDrivePickerModal.tsx`, which connects a **personal Microsoft account** (OAuth 2.0 Authorization Code + PKCE, no client secret) and lists files from a configured folder. Picking a file sets `linkUrl` to its OneDrive web URL. The button appears in two places: the Resumes page's "New resume" dialog (`ResumesPage.tsx`) and the job page's combined "Edit application" dialog (`JobDetailPage.tsx` — action date, resume, cover letter, notes — where a picked resume URL is saved as the application's `resumeLinkUrl`, a picked cover letter URL as `coverLetterLinkUrl`).
- Backend: `backend/src/Controller/OneDriveController.php` (`GET /api/onedrive/status|auth-url|callback|files`, `PUT /api/onedrive/settings`, `POST /api/onedrive/disconnect`) + `backend/src/Service/OneDriveClient.php`. `auth-url` accepts a `returnTo` query param (same-site path only, stored in the session) and the callback redirects back to it — `/resumes` when absent — as `<path>?onedrive=connected|error`. Both pages consume that flag via the shared helpers in `frontend/src/oneDrive.ts` (`consumeOneDriveFlag` + `oneDriveError`): the resumes page auto-opens the picker, the job page opens the Edit application dialog plus picker (for either resume or cover-letter target, after the job has reloaded).
- Tokens are stored per-user in the `one_drive_token` table (plaintext, never serialized via API). Config missing → 503; not connected / token expired → 409 (server deletes the token); upstream errors → 502. `OneDriveException::isConfigurationProblem()` distinguishes 503 vs 502.
- Folder lists are per-user in `user_settings.one_drive_folders` (JSON list, one row per user via `UserSettings`/`UserSettingsRepository`). `GET /api/onedrive/status` and `/files` use the saved list; users with no settings row fall back to the comma-separated `ONEDRIVE_FOLDER_PATH` default. The Profile page (`frontend/src/pages/ProfilePage.tsx`, route `/profile`, reached by clicking the user name in the header) adds/removes folders and saves through `PUT /api/onedrive/settings`; the picker links there when the list is empty.
- Config lives in `backend/.env(.local)`: `ONEDRIVE_CLIENT_ID` (empty = feature disabled; status returns `clientConfigured: false`), `ONEDRIVE_TENANT` (default `consumers` — personal accounts only), `ONEDRIVE_FOLDER_PATH` (default folder list for users without saved settings; empty = OneDrive root), `ONEDRIVE_REDIRECT_URI` (optional; otherwise auto-derived from the request host), `ONEDRIVE_CLIENT_SECRET` (optional; required when the app is registered as a confidential "Web" client, omitted and sent only when set — empty keeps the public-client PKCE flow secret-free). Azure app registration notes (redirect URIs, scopes `offline_access Files.Read`, optional `User.Read`) are documented in `backend/.env.example`.

## Networking tracker

- The Networking page (`frontend/src/pages/NetworkingPage.tsx`, route `/networking`) has three views — **All contacts** (name A–Z), **Recent activity** (sorted by last contact, newest first), **Needs follow-up** (people whose latest contact is flagged) — plus a search box that matches name, company, or email. Pure filtering/sorting/payload helpers live in `frontend/src/networking.ts` (`filterPeople`, `sortByLastContact`, `needsFollowUpPeople`, `toPersonPayload`, `toContactPayload`) and are unit-tested in `frontend/src/networking.test.ts`.
- Entities `Company`, `Person`, `Contact` are all user-scoped. `company` has a unique index on `(user_id, name)`; `person` links to a nullable `company`; `contact` is a child of `person` (DATE `date`, TEXT `description`, bool `needs_follow_up`, default false). Person's `lastContact`/`needsFollowUp`/`companyId`/`companyName` are virtual getters — they only serialize when `person.read`/`person.list` is combined with `contact.read` (Person index/show use `['person.list','contact.read']` / `['person.read','contact.read']`).
- API (backend `src/Controller/`): `/api/companies`, `/api/people`, and `/api/people/{personId}/contacts` CRUD. Group annotations: `company.read`, `person.list`, `person.read`, `contact.read`. People accept `companyId` **or** `companyName` (find-or-create) or neither; cross-user company → 422 `Company not found.`; 409 on duplicate company name / delete-while-linked (linked = has jobs or people).
- **Job→Company real FK**: `Job.company` (string) remains the display source of truth; `Job.companyRef` ManyToOne (`job.company_id`, nullable, groups `job.list`/`job.read` as `companyId`) is auto-set on job create/update via find-or-create by name (`JobController::findOrCreateCompany`).
- One-time backfill: `php bin/console app:backfill-networking` (`backend/src/Command/BackfillNetworkingCommand.php` + `backend/src/Service/NetworkingBackfillService.php`). Idempotent — creates a company per distinct `(user, lowercase companyName)` from `Job.company`, links jobs to it, and creates a person per interviewer from all interviews' `interviewers` (skipping blanks, duplicates within an interview, and already-known `(user, name, company)` people). It also creates a `Contact` per interviewer for each of their interviews — date = interview date (time dropped), description = interview notes, `needsFollowUp` = false — deduped against any existing contact for that `(person, date, description)`, so re-running it adds only the missing contacts (and back-links matching existing contacts to their `interview`).
- Interview create/update is self-syncing: `InterviewController` (`backend/src/Controller/InterviewController.php`) accepts `interviewers` as an array **or** a comma-separated string, and for each interviewer finds-or-creates a `Person` (scoped to the user, linked to the job's `companyRef`, deduped via `PersonRepository::findByNameAndCompany`) plus a `Contact` on the interview date with `description` = interview notes (`needsFollowUp` false, deduped via `ContactRepository::findForPersonAndDate`). Both lookups are skipped when the person is brand-new (no id yet, so `findForPersonAndDate` is never called with an id-less entity). Deriving an interview from a contact is tracked by `Contact.interview` (nullable FK, `onDelete: SET NULL`, not serialized), so editing an interview (date/notes) updates its derived contacts instead of piling up new ones; interviewers removed from the list leave their contacts in place as history.

## Reports

- The Reports page (`frontend/src/pages/ReportsPage.tsx`, route `/reports`) loads `GET /api/reports` (`backend/src/Controller/ReportController.php`), which is scoped to the authenticated user and returns `totalApplications` (application records), `applicationsWithInterview` (distinct applications with at least one interview), `totalActivities`, `totalHours`, and `weekly` (`[{weekStart, activityCount, hours}]`, grouped by Monday and sorted ascending).
- Weekly aggregation happens in `ReportController` from `JobActivityRepository::findByUser()`. Charts are dependency-free CSS/SVG bar charts (`frontend/src/components/WeeklyBarChart.tsx`); pure label/number/point helpers live in `frontend/src/reports.ts` (`shortWeekLabel`, `compactNumber`, `weeklyChartPoints`) and are unit-tested in `frontend/src/reports.test.ts`.

## Deployment

- `deploy.sh` (server): `git pull` + `composer install --no-dev` + optional frontend build + migrations + cache warm.
- `push-assets.ps1` / `push-assets.sh` (local): build frontend and SCP `backend/public/build/` to the server. Connection settings come from the **root `.env`** as `REMOTE_USER`, `REMOTE_HOST`, `REMOTE_PATH`, `REMOTE_PORT`.
- `deploy.ps1` (local, Windows): one-step deploy — SSHs to the server to run `deploy.sh --no-build`, then runs `push-assets.ps1`. Reads the same root `.env` settings (`REMOTE_USER`, `REMOTE_HOST`, `REMOTE_PORT`, `REMOTE_DEPLOY_DIR` = repo root on the server).
- Production runs Apache + php:8.3; docker-compose `web` service runs migrations and cache warmup on boot.
