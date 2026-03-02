# AGENTS.md

## Cursor Cloud specific instructions

### Project overview

OpnForm is a no-code form builder with two main services: a **Laravel 11 API** (`api/`) and a **Nuxt 3 client** (`client/`). Development uses Docker Compose (`docker-compose.dev.yml`) which runs PostgreSQL, the API (PHP-FPM), the client (Nuxt dev server), and an Nginx ingress.

### Starting the dev environment

```bash
# Docker must be running first (see Docker gotcha below)
cd /workspace && docker compose -f docker-compose.dev.yml up -d
```

Wait ~60s for the client container to finish `npm install` + Nuxt build on first start. The app is then available at `http://localhost:3000` (client) and `http://localhost` (API via Nginx).

On first run, navigate to `http://localhost:3000/setup` to create the admin account.

### Docker gotcha (nested containers)

This cloud environment runs inside a container, so Docker requires `fuse-overlayfs` storage driver and `iptables-legacy`. The daemon config at `/etc/docker/daemon.json` is already set. To start dockerd:

```bash
sudo dockerd &>/tmp/dockerd.log &
sleep 3
sudo chmod 666 /var/run/docker.sock
```

### Stale Docker API image

The published `jhumanj/opnform-api:dev` image may lag behind the repo's `composer.json`. If the API container fails with missing class errors (e.g. `TwoFactorServiceProvider not found`), run:

```bash
docker exec opnform-api sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
docker exec opnform-api composer install --no-interaction --optimize-autoloader
docker restart opnform-api
```

### Running tests

- **API tests** use SQLite in-memory (no PostgreSQL needed). Run locally:
  ```bash
  cd /workspace/api && ./vendor/bin/pest
  ```
  Before first local run, ensure `storage/` and `bootstrap/cache/` are writable (`sudo chmod -R 777 api/storage api/bootstrap/cache`) and clear any Docker-cached config (`rm -f api/bootstrap/cache/config.php`).

- **Client tests** (Vitest):
  ```bash
  cd /workspace/client && npm run test -- --run
  ```
  If `.nuxt/` has permission issues, run `sudo chown -R $(whoami) client/.nuxt`.

- **Client lint** (ESLint):
  ```bash
  cd /workspace/client && npm run lint
  ```

### Key dev commands reference

See `client/package.json` scripts and `api/composer.json` scripts for full list. The lockfile is `package-lock.json` so use `npm` (not pnpm/yarn) for the client.
