# Job Track

A job application tracker with a Symfony 7.4 API backend and a React + Vite + TypeScript frontend.

## Tech stack

- **Backend:** PHP 8.2+, Symfony 7.4, Doctrine ORM, MySQL 8.4
- **Frontend:** React 18, TypeScript, Vite 5
- **Deployment:** Docker (php:8.3-apache + MySQL 8.4)

## Project structure

```
backend/          Symfony app (entities, controllers, config, public/)
frontend/         React app (src/, vite.config.ts, tsconfig.json)
docker/           Dockerfile, Apache vhost, entrypoint script
docker-compose.yml
```

## Prerequisites

- PHP 8.2+ with the ctype, iconv, and pdo_mysql extensions
- Composer
- Node.js 22+ and npm
- Docker (for MySQL)

## Local development

### 1. Start MySQL

```bash
docker compose up -d db
```

This starts MySQL 8.4 on port **3307** with database `job_track`, user `app`, password `app`.

### 2. Install backend dependencies

```bash
cd backend
composer install
```

### 3. Run database migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Start the Symfony dev server

```bash
php -S 127.0.0.1:8000 -t public
```

### 5. Install and build the frontend

```bash
cd ../frontend
npm install
npm run build
```

This outputs production assets to `backend/public/build/`.

### 6. Open the app

Navigate to [http://localhost:8000](http://localhost:8000).

On first load you'll see the **Setup** page — create your administrator account. You'll then be logged in and redirected to the dashboard.

### Frontend development server (optional)

For hot-reload during frontend development:

```bash
cd frontend
npm run dev
```

This starts Vite on port 5173 with an `/api` proxy to `localhost:8000`. Visit `http://localhost:5173/build/` for the dev server, or keep using `http://localhost:8000` (the production build is served from Symfony).

## Docker deployment (production)

```bash
docker compose up -d
```

This starts both the `db` (MySQL) and `web` (Apache) services. The web container waits for the database, runs migrations, warms the cache, and serves the app on port **8080**.

To auto-create an admin user on first boot, pass environment variables:

```bash
ADMIN_EMAIL=you@example.com \
ADMIN_NAME=Admin \
ADMIN_PASSWORD=changeme \
docker compose up -d
```

The Docker build expects the frontend to already be built into `backend/public/build/`. If you need to rebuild it inside the container, the `Dockerfile` includes a Node.js build stage that runs `npm install && npm run build` in `frontend/`.

## API overview

All endpoints are under `/api`. Authentication is session-based with a SameSite=Lax cookie. State-changing requests (except login) require an `X-CSRF-TOKEN` header — the token is returned by login, `/api/auth/me`, and `/api/setup/status`.

| Endpoint | Methods | Description |
|---|---|---|
| `/api/setup/status` | GET | Check if setup is needed |
| `/api/setup` | POST | Create the first admin |
| `/api/auth/login` | POST | Log in (JSON `{email, password}`) |
| `/api/auth/me` | GET | Current user + CSRF token |
| `/api/auth/logout` | POST | Log out |
| `/api/job-searches` | GET, POST | List / create job searches |
| `/api/job-searches/{id}` | GET, PATCH, DELETE | View / update / delete a job search |
| `/api/job-searches/{id}/jobs` | GET, POST | List / create jobs in a search |
| `/api/jobs/{id}` | GET, PATCH, DELETE | View / update / delete a job |
| `/api/jobs/{id}/application` | GET, POST | View / create the application for a job |
| `/api/applications/{id}` | PATCH, DELETE | Update / delete an application |
| `/api/applications/{id}/resume-file` | POST | Upload a resume file (multipart) |
| `/api/applications/{id}/resume/download` | GET | Download the resume file |
| `/api/applications/{id}/interviews` | GET, POST | List / create interviews |
| `/api/interviews/{id}` | PATCH, DELETE | Update / delete an interview |
| `/api/resumes` | GET, POST | List / create resumes (library) |
| `/api/resumes/{id}` | PATCH, DELETE | Update / delete a resume |
| `/api/resumes/{id}/download` | GET | Download a resume file |
| `/api/cover-letters` | GET, POST | List / create cover letters |
| `/api/cover-letters/{id}` | PATCH, DELETE | Update / delete a cover letter |
| `/api/users` | GET, POST | List / create users (admin only) |
| `/api/users/{id}` | DELETE | Delete a user (admin only) |

## License

This project is licensed under the GNU General Public License v3.0 — see the [LICENSE](LICENSE) file for details.
