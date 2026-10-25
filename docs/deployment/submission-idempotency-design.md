---
title: "Durable submission replay — local implementation and evidence"
description: "Local operation/outbox implementation with synthetic PostgreSQL and Redis crash tests; production rollout remains blocked."
---

The operation/outbox implementation is now part of the hosted runtime. The dated
qualification notes below describe its development history; current delivery and
rollback instructions are in [the production guide](scaleway-production.md).


Updated October 17, 2026. The isolated preparation branch now implements this in
`StoreFormSubmissionJob`, with new application migrations. The historical
prototype and baseline findings below remain separate evidence.
No production database migration, source queue mutation or integration send was
performed. It does not claim exactly-once external delivery.

## Original runtime: observed problem

`job-retry-diagnostic.php` bootstraps the actual application in a network-disabled
test image, requires SQLite `:memory:` and testing mode, creates only fictional
records, and fakes queue/mail/notifications and `FormSubmitted` effects.
On the original, unchanged runtime, replaying the original serialized
`StoreFormSubmissionJob` payload gave:

- First delivery: one submission.
- Replay: two submissions and two faked `FormSubmitted` events.
- Diagnostic status: `BLOCKER-REDELIVERY-NOT-IDEMPOTENT`, exit 2.

The job's in-memory `submissionId` is updated after save, but a queue redelivery
restores the original serialized payload. A new record and public ID can be
created again. The event is outside the SQL transaction. File processing also
starts before that transaction. A Redis lease cannot close the SQL/event/ACK crash
windows. The diagnostic is a real application replay, not a real SQS/Redis worker
crash; no actual integration effect was executed.

## Implemented local contract

1. **Stable operation identity.** Generate a server operation UUID in the job
   constructor, before queue serialization. One operation identifies one accepted
   submission/update snapshot, not all responses to a form or all saves by a
   respondent. Bind it to form ID, operation kind, optional existing submission ID
   and canonical payload SHA256. Do not trust a client-supplied submission ID as
   an idempotency key.
2. **Old payloads.** A missing new field must deserialize safely. For actual
   queued old payloads, use the transport payload's stable job UUID after verifying
   the locked implementations and inventories. The Redis fixture below verifies
   stability across actual worker SIGKILL; the SQS/legacy-payload contract still
   needs separate validation. If no stable UUID
   exists, quarantine/reconcile or drain before migration; do not invent an
   attempt-time UUID and call it durable. Synchronous new jobs receive the
   constructor identity. The implemented fallback is tested with a synthetic real Redis payload;
   live source SQS payloads remain unqualified and must be inventoried/drained.
3. **Operation row and constraint.** Add a separate operation table with a unique
   `(form_id, operation_key)`, payload fingerprint, submission ID and durable
   processing state. Reserve/lock the row in SQL. A replay returns its existing
   result; a key reused for different content fails. Serialize concurrent attempts
   by the unique constraint plus row lock, not by a cache TTL.
4. **Atomic SQL and outbox.** Commit submission data, generated IDs and an outbox
   event together. A crash before commit leaves none; a crash after commit but
   before queue ACK finds the committed operation and does not insert again.
   Preserve existing authorization, partial→completed/admin-edit semantics,
   attribution, auto-increment allocation and model/file-registry hooks. Distinct
   legitimate updates must remain distinct operations.
5. **Files and signatures.** Resolve the operation before side effects. Use stable
   destination keys derived from operation/field identity, preserve tmp/original
   bytes until SQL finalization, and record pending temporary-file cleanup on the committed operation. Replaying
   after a move must recover the same object rather than lose a temp reference or
   generate another random signature filename. Storage and SQL are not one
   transaction; cleanup must retain/reconcile orphans until success is durable.
6. **Per-effect delivery state.** An outbox poller takes bounded batches and atomically claims
   each pending row with a conditional state update and UUID token. Track one durable delivery row per event/integration
   effect and a stable event/idempotency reference. Use provider-supported
   idempotency keys only where the actual provider contract supports them.
7. **Ambiguous effects.** A claim that might have sent an external effect and lost
   its ACK becomes `ambiguous` after lease expiry, not automatically pending.
   Keep the same event/reference for reconciliation. SMTP/webhooks without
   receiver idempotency cannot provide exactly-once delivery; operator/provider
   evidence must decide whether to retry. The current conservative implementation also quarantines thrown errors; it
   does not yet classify confirmed pre-send failures for automatic backoff. Do not silently discard ambiguous deliveries.

This is a structural change: schema, ingestion flow, file staging, queued event
dispatch and integration delivery must be reviewed together. Deduplicating only
the SQL insert can still lose an event after commit; suppressing every repeated
event can lose a legitimate update; blindly retrying external delivery can duplicate
a notification. No partial workaround is presented as migration readiness.

## Executed PostgreSQL prototype

The review schema is `tests/submission-idempotency-prototype.sql`, not an
application migration. `submission-idempotency-prototype.py` creates one NEW
network-none, read-only-root, tmpfs PostgreSQL container on the preparation host:

```sh
python3 /root/opnform-migration-tools/submission-idempotency-prototype.py \
  --output "/root/opnform-migration-tools/idempotency-proof-$(date -u +%Y%m%dT%H%M%SZ).json"
```

The real database tests cover transaction rollback before commit, replay after
an unknown ACK, eight concurrent clients obtaining one submission/outbox,
different content under the same key, distinct legitimate operations, two effect
workers claiming once, and a fake accepted effect with ambiguous local ACK that
cannot be blindly claimed again. The container is removed afterward.

The prototype uses JSONB equality to bind a key to content and an SQL fake-effect
table. Production design uses an explicit canonical fingerprint and must qualify
payload normalization. The prototype does not implement the actual job, legacy
transport identity, partial/edit semantics, file staging, outbox deployment,
lease watchdog, real delays/DLQ or provider behavior. Those remain open gates.
Existing release-controller tests separately cover start requests with unknown
outcome, queued remote jobs, reconciliation and exclusion of concurrent releases.

## Executed Laravel/Redis transport check

`queue-transport-fixture.py` runs the actual Laravel queue worker from the pinned
amd64 runtime against a NEW disposable Redis. Its generated probe job dispatches
no submission, notification or integration. A dedicated internal bridge and
host-input/forwarding guard isolate the fixture; it publishes no port and removes
its containers/network/nft table afterward.

Seventeen checks pass: delayed dispatch is not processed by the early worker,
becomes due once, then a separate probe worker is killed with SIGKILL after a
Redis marker but before queue ACK. The payload stays reserved and is reclaimed
after the fixture lease, runs as attempt2 with exactly the same nonempty job UUID,
and leaves no reservation after success. The fixture uses retry_after4 seconds
to bound test time; actual production preparation keeps3700 seconds.

```sh
python3 /root/opnform-migration-tools/queue-transport-fixture.py
```

This establishes transport replay and stable identity for this locked Redis
driver, not application idempotency or live SQS/DLQ behavior. Re-run the crash
cases with the actual operation/outbox/file implementation once integrated.

## Actual application validation on local PostgreSQL

`submission-operation-fixture.py` runs the rebuilt private preparation runtime,
not the SQL prototype. It creates fresh network-none PostgreSQL and Redis
containers with temporary data, sharing only a dedicated Unix-socket volume.
Application containers also have no network. A strict marker, database name,
Unix-socket path and environment guard precede migrations or writes. No source
queue or native preparation database is used. Exact fixture resources are removed.

```sh
python3 deploy/scaleway/build-operation-candidate.py /private/path/image-proof.json
python3 deploy/scaleway/submission-operation-fixture.py /private/path/operation-proof.json
```

The local candidate is a source-layer rebuild on the cached production runtime,
with twelve application/config/migration files hash-checked inside the image.
It includes the S3 signing correction. That first candidate was arm64 and unpublished; the native follow-up below
qualifies the same twelve files on amd64 without claiming a clean full build. The script checks available disk
space, performs no pull or network build step, and never prunes shared resources.

Coverage includes eight simultaneous copies of one serialized job; one SQL row,
one operation and one event; rejection of a different body for a committed key;
SIGKILL before SQL commit; SIGKILL after commit before outbox dispatch; recovery
by replay and by the bounded sweeper; thrown and interrupted event dispatch;
eight competing integration claims; thrown and interrupted fictional effects;
stale claims becoming ambiguous; legacy payload identity; partial completion and
separate admin edits. Real Laravel/Redis worker crash and ACK checks use a
four-second fixture reservation, without changing the runtime's 3700 seconds.
Queue, mail, notifications and HTTP are faked except the explicitly isolated Redis
job transport. Fictional effect calls are recorded in fixture SQL tables.

The Pest suite additionally exercises file/signature rollback and deterministic
paths, failed-copy rollback without temp deletion, same-instance replay, missing
legacy identity, cross-form integration rejection, upload-byte fingerprints and
existing submission/edit/identifier/attribution behavior. UploadedFile fingerprints
use bytes and public metadata, not temporary paths or resource handles.

## State and operational meaning

- Operation identity is generated before serialization; distinct accepted requests
  still have distinct keys. This does not deduplicate a browser retry that creates
  a new job. A conflicting payload for the same key is rejected before effects.
- Submission SQL, generated identifiers and the operation snapshot commit together.
  Files are outside SQL: copy failures stop commit; successful destinations can
  survive rollback and are reused. Temp originals are deleted only after commit.
  Abandoned orphan cleanup and storage retention still need a reviewed policy.
- `forms:dispatch-submission-outbox --limit=100` runs once per minute. It resumes
  pending events and deferred temp cleanup. It marks dispatch claims older than
  five minutes and integration claims older than 3700 seconds as `ambiguous`.
- Outbox `dispatched` means the application event dispatch returned. Integration
  `invoked` means the existing handler returned, including handlers that catch
  provider failures themselves. Neither state proves provider delivery. Existing
  integration events/email delivery tracking remain the delivery evidence.
- Repeated listeners cannot claim an invoked, invoking or ambiguous effect again.
  Unknown outcomes have no automatic retry/reset command. Operator/provider
  reconciliation, alerting and any safe confirmed-failure retry path remain open.
- Old `FormSubmitted` events with no operation ID retain their historical listener
  behavior. Old unidentified submission jobs fail before writes. Neither category
  is automatically made safe by deploying the new schema.

## Rollout gates — still NO-GO

The schema has only been migrated in disposable synthetic databases. A future
production rollout needs explicit approval, old producer/worker drain and source
queue reconciliation, then schema before new application workers/scheduler, with
compatible code deployed together. Never run old and new submission workers over
the same replayable backlog and assume the new guard covers old writes. Do not
roll back to old writers or remove operation history while queue replays remain
possible. Operation snapshots retain submission data and need the same retention
and access review as submissions.

Remaining gates include a clean full build/CI and private immutable release; real
source SQS/DLQ/legacy payload inventory; provider delivery/ambiguous-outcome reconciliation
and alerts; source/provider S3 CORS/ACL/lifecycle qualification; representative
restore and cutover rehearsals. No build or synthetic test authorizes publication,
production migrations, client-data transfer, queue mutation or traffic changes.

## Native amd64 follow-up — 17 October 2026, 21:02 Paris

The dedicated OpnForm server 115460 rebuilt a separate unpublished source-layer
candidate on its cached amd64 runtime. Twelve current application/configuration
files were SHA256-checked inside the image and against the local worktree. The
image identity reported by Docker is
`sha256:fea8a07d7e1fd8588db7912cbc21562c282a6b524df642be9c3f0b1d30d00b85`.
This is a cached-runtime source-layer build, not a clean checkout/dependency build
or registry provenance. No existing preview image tag was replaced.

On that exact candidate, 26 PostgreSQL/Redis operation checks, all 19 runtime
scenarios and 25 offline S3 cryptographic contract checks passed natively. The
operation fixture uses network-none containers and a private Unix-socket volume;
the runtime suite uses its own internal bridge and temporary host deny rules.
No client data or real provider delivery is exercised. PostgreSQL/Redis fixture
containers, socket volumes, runtime network and temporary firewall tables were
removed. The final host check found all 13 existing containers present, the old
preview healthy, no failed system units and the same boot ID. No reboot, native
application deployment, source queue mutation, client copy, GitHub publication,
AWS/DNS mutation or production switch was performed.

Private evidence and executable runner copies are retained under
`operations/opnform-preparation-20261016/native-qualification/` in the existing
private Infra-Scaleway folder. The checkpoint and remaining-work inventory point
to this proof. Historical arm64 results above remain historical; native synthetic
qualification is now complete, while the rollout gates remain NO-GO.


## Source queue plan and offline SQS adapter follow-up

The [source drain and reconciliation plan](scaleway-source-queue-reconciliation.md)
records the source/mapping, producer fence sequence, ambiguous provider/recipient
decision matrix and remaining approvals. A later central login renewal enabled
fresh bounded metadata reads at22:07Paris. Source remains active, not fenced or
qualified as drained; no live queue payload was read.

Two additional network-none Pest tests pass (10 assertions): real locked Laravel
SqsJob objects preserve body UUID across receipt/attempt changes, while a missing
body UUID fails before SQL even if SQS MessageId looks valid. No AWS request is
possible in the fictional SDK handler. These are additional adapter checks, not a
repeat of the qualified native runtime, and the twelve application hashes remain
unchanged. No historical payload or already-processed source job is certified by
these tests; no operation history exists for writes made by the old code.
