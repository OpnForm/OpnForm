---
title: "Hosted production on Scaleway"
description: "Build, verify and explicitly promote the OpnForm image pair through private Coolify access."
---

# Hosted production delivery

The hosted application uses PostgreSQL, one persistent Redis process, private S3
storage and five Compose roles managed by Coolify: API, frontend, migration,
jobs and scheduler. Self-hosted Docker Hub releases remain a separate workflow.

## Merge and publication

`CI/CD Pipeline` runs the existing application/security/browser checks and the
reusable `Scaleway runtime` gate. The runtime tests in `tests/Deployment` exercise
the actual production Laravel configuration in the complete Linux AMD64 image pair,
with disposable PostgreSQL/Redis dependencies.
It checks the packaged sources, standard Laravel cache/sessions/locks, explicit
Redis saturation without eviction, JWT revocations, queue drain, scheduler singleton,
migrations and authenticated SSR. It cannot reach
production databases, buckets, mail or payment providers.

After a successful push to `main`, `Publish validated Scaleway images` loads
the exact pair exercised by that gate and publishes private GHCR images with
commit tags and immutable digests. The `opnform-coolify-release` artifact contains
`release.json`, binding both digests to the commit and CI run/attempt. No `latest`
tag is used for hosted deployment. Package visibility is checked before and after
publication. The job has no Coolify credentials, SSH keys or AWS credentials.

**A merge does not restart production.** The former Vapor and Amplify deployment
steps are removed. Their compatibility build tests do not deploy to AWS. Existing
AWS origins and recovery data are retained until a separate retirement decision.

## Private promotion

Use `deploy/scaleway/coolify-service-release.py` from an operator machine with its
existing GitHub authentication, local Docker engine and pinned Tailscale SSH
access. GitHub runners never receive an administrator token for shared Coolify.
The target allowlist and secrets remain outside this repository.

1. Download `opnform-coolify-release` from the completed successful main CI run.
2. Prepare a private target JSON, following
   `deploy/scaleway/service-target.example.json`. Supply the exact Coolify team,
   project, server and service UUID; expected current Docker image identities;
   canonical URLs; and private SSH config/aliases. Resolve both SSH aliases to
   their literal Tailscale addresses with strict host pins. Do not use public SSH.
3. Run the default, read-only plan:

   ```sh
   python3 deploy/scaleway/coolify-service-release.py release.json \
     --target /absolute/private/opnform-target.json
   ```

   The plan checks that the exact CI run and attempt succeeded on current main,
   the images belong to OpnForm and are pinned by digest, the private service
   identity matches, and all four running roles match the expected healthy pair.
   It neither pulls images nor changes Coolify configuration or containers.

4. A promotion is an **explicit operational deployment**, separate from merge.
   Review migration compatibility, a fresh recoverable backup and the application
   health before selecting it. It can recreate API/frontend containers; use a
   controlled window if that release cannot tolerate their restart. This tool
   does not claim blue/green deployment or guaranteed zero downtime.

   ```sh
   python3 deploy/scaleway/coolify-service-release.py release.json \
     --target /absolute/private/opnform-target.json \
     --apply --allow-service-restart \
     --journal /absolute/private/opnform-promotion.json
   ```

   The operator's Docker engine pulls both images and verifies their commit
   labels. The immutable registry digests are mapped to local tags containing the complete
   digest, then streamed through pinned SSH to the target. Docker archives do not
   reliably preserve registry digest references between engine types, so layer and
   runtime-configuration fingerprints are compared after loading. No GitHub token
   is installed on production. Private package reads require an operator registry
   login with `read:packages`; never store that token in the target JSON or Git.
   Coolify saves the previous raw Compose configuration privately before changing
   only the five image references. Existing secrets, networks, health checks,
   domains and frontend environment separation are preserved.

   The isolated migration role must become healthy before any old role is
   stopped. Its process intentionally sleeps after writing its success marker;
   the promoter checks readiness, stops and removes only that temporary role.
   Workers and scheduler drain with their long stop grace. API, frontend, jobs
   and scheduler are then promoted with per-role health gates; final public API
   and frontend revisions must match the selected commit. There is no global
   Coolify restart, AWS reconfiguration, DNS change, Redis reset or bucket purge.

Promotion failures keep a private journal and a service-wide lease in the Coolify
controller. A second operator cannot overlap the same service even when using a
different local journal. The lease is released only after successful public
revision checks; failures require inspection and are not retried automatically.
Inspect migration state, active jobs and the previous raw configuration before
continuing. After any schema or new application write, reverting DNS/images alone
is insufficient: refreeze and reconcile SQL, Redis, files and external effects.

## Runtime configuration

- Preserve `APP_KEY`, JWT, Passport and SSR keys. Backend credentials belong only
  in backend roles, with the intended shared SSR secret in the frontend.
- `OPNFORM_STATE_MODE=redis-fresh` preserves the current cache/session/JWT bindings.
  Cache uses Laravel’s standard `RedisStore`. The small `UnifiedRedisJwtStorage`
  adapter only selects the existing auth store for JWT revocations; changing it to
  the default cache would lose the effective blacklist namespace. Do not change
  these bindings or clear Redis on an application release. There is no import mode.
- Submission operations/outbox are already active in production. Keep their
  migrations, dispatch sweep and integration claims. Removing them would change
  replay and side-effect behavior; they are not an inactive migration prototype.
- The single Redis process uses DB0 for queues, DB1 for disposable cache, DB2 for
  authentication and DB3 for state. Keep AOF/always, noeviction, non-overlapping
  prefixes and a queue reservation longer than the worker execution timeout.
- Frontend must retain its native Node PATH/entrypoint and canonical-host HTTP
  health check. Its image includes an explicit Node entrypoint and command.
- Keep the actual private service subnet in `TRUSTED_PROXIES`. Trust forwarded
  headers only from the authorized old/new Caddy addresses at the ingress.
- Caddy can use an explicit native origin IP with the canonical TLS server name
  and Host header, avoiding stale DNS connections. Never disable TLS verification.
- Preserve the canonical `www` redirect in persistent proxy configuration.

Coolify SQL backups, coherent Redis backups and encrypted Caddy/configuration
backups are separate protections. Retain recovery keys separately and verify
restores; SQL backups do not include Redis or files. Keep operational IDs, keys,
customer datasets, production inventories and dated worklogs in the private
infrastructure workspace and operational documentation, outside this repository.
