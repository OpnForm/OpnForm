import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, writeFileSync, mkdtempSync, mkdirSync, chmodSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { createRequire } from 'node:module'
import { evaluateNpmAudit } from '../audit-npm.mjs'
const require = createRequire(new URL('../../client/package.json', import.meta.url))
const { parse } = require('yaml')
const policy = JSON.parse(readFileSync(new URL('../npm-exceptions.json', import.meta.url)))
const reviewed = policy.exceptions.find(entry => entry.package === 'braces')
const audit = () => ({
  auditReportVersion: 2,
  metadata: { vulnerabilities: { total: 2 } },
  vulnerabilities: {
    braces: { severity: 'high', nodes: reviewed.nodes, via: [{ name: 'braces', severity: 'high', url: reviewed.advisory }] },
    nuxt: { severity: 'high', nodes: ['node_modules/nuxt'], via: ['braces'] },
  },
})
const lock = { packages: Object.fromEntries(reviewed.nodes.map(node => [node, { version: reviewed.version }])) }
const check = report => evaluateNpmAudit(report, lock, policy, '2026-10-23')

test('accepts only the reviewed advisory and its inherited parents without filtering raw findings', () => {
  const report = audit()
  const before = JSON.stringify(report)
  assert.equal(check(report).affectedPackages, 2)
  assert.equal(JSON.stringify(report), before)
})
test('rejects a new advisory on an already reviewed package', () => {
  const report = audit()
  report.vulnerabilities.braces.via[0].url = 'https://github.com/advisories/NEW'
  assert.throws(() => check(report), /Unreviewed advisory/)
})
test('rejects new parents and changed paths, versions or severities', () => {
  const report = audit()
  report.vulnerabilities.unknown = report.vulnerabilities.nuxt
  report.metadata.vulnerabilities.total = 3
  assert.throws(() => check(report), /Unreviewed affected package/)
  const path = audit()
  path.vulnerabilities.braces.nodes = ['node_modules/new/node_modules/braces']
  assert.throws(() => check(path), /Unreviewed advisory path/)
  const version = structuredClone(lock)
  version.packages[reviewed.nodes[0]].version = '3.0.4'
  assert.throws(() => evaluateNpmAudit(audit(), version, policy, '2026-10-23'), /Unreviewed vulnerable version/)
  const severity = audit()
  severity.vulnerabilities.nuxt.severity = 'critical'
  assert.throws(() => check(severity), /Changed inherited severity/)
})
test('rejects expired exceptions, cycles and registry errors', () => {
  assert.throws(() => evaluateNpmAudit(audit(), lock, policy, '2026-11-07'), /Expired/)
  const cycle = audit()
  cycle.vulnerabilities.braces.via = ['nuxt']
  assert.throws(() => check(cycle), /cycle/)
  assert.throws(() => check({ error: { code: 'ENETUNREACH' } }), /failed npm audit/)
})

const pipeline = parse(readFileSync(new URL('../../.github/workflows/ci-cd.yml', import.meta.url), 'utf8'))
test('docs-only PRs execute the stable required check without path filters', () => {
  assert.ok(Object.hasOwn(pipeline.on, 'pull_request'))
  assert.equal(pipeline.on.pull_request, null)
  assert.equal(pipeline.jobs['release-readiness'].name, 'Release readiness')
  assert.equal(pipeline.jobs['release-readiness'].if, 'always()')
})
test('hosted and self-hosted publications depend on the security and QA aggregate', () => {
  for (const name of ['publish-scaleway-images', 'publish-docker-images']) assert.deepEqual(pipeline.jobs[name].needs, ['release-readiness'])
  assert.ok(pipeline.jobs['release-readiness'].needs.includes('dependency-security'))
  assert.ok(pipeline.jobs['release-readiness'].needs.includes('scaleway-runtime'))
  assert.equal(pipeline.jobs['dependency-security'].uses, './.github/workflows/dependency-security.yml')
  assert.equal(pipeline.jobs['production-deploy'], undefined)
  assert.equal(pipeline.jobs['scaleway-runtime'].uses, './.github/workflows/scaleway-validation.yml')
  const publication = pipeline.jobs['publish-scaleway-images']
  assert.match(publication.if, /github\.ref == 'refs\/heads\/main'/)
  assert.match(publication.if, /github\.event_name == 'push'/)
  assert.equal(publication.permissions.packages, 'write')
  const raw = JSON.stringify(publication)
  for (const forbidden of ['VAPOR_API_TOKEN', 'AMPLIFY_DEPLOY_URL', 'COOLIFY_TOKEN', 'vapor deploy']) {
    assert.equal(raw.includes(forbidden), false)
  }
})
test('general PHP suite is honestly SQLite and real database checks gate releases', () => {
  assert.match(pipeline.jobs.tests.name, /SQLite/)
  assert.equal(pipeline.jobs.tests.strategy, undefined)
  assert.equal(pipeline.jobs.tests.steps.find(step => step.name === 'Run tests (Unit and Feature)').env.DB_CONNECTION, 'sqlite')
  assert.deepEqual(pipeline.jobs.database_compatibility.strategy.matrix.connection, ['pgsql', 'mysql'])
  assert.ok(pipeline.jobs['release-readiness'].needs.includes('database_compatibility'))
  const check = pipeline.jobs.database_compatibility.steps.find(step => step.name?.startsWith('Check real PDO'))
  assert.equal(check.env.APP_ENV, 'database-compatibility')
  assert.match(check.run, /check-database-compatibility\.php/)
})
test('production deployment observation follows only successful main push CI without administrator credentials', () => {
  const workflow = parse(readFileSync(new URL('../../.github/workflows/scaleway-deployment.yml', import.meta.url), 'utf8'))
  assert.deepEqual(workflow.on.workflow_run.workflows, ['CI/CD Pipeline'])
  assert.deepEqual(workflow.on.workflow_run.types, ['completed'])
  assert.deepEqual(workflow.on.workflow_run.branches, ['main'])
  const job = workflow.jobs['verify-live-release']
  for (const condition of ["conclusion == 'success'", "event == 'push'", "head_branch == 'main'", "head_repository.full_name == 'OpnForm/OpnForm'"]) assert.ok(job.if.includes(condition))
  const raw = JSON.stringify(workflow)
  for (const secret of ['COOLIFY_TOKEN', 'SSH_PRIVATE_KEY', 'VAPOR_API_TOKEN', 'AMPLIFY_DEPLOY_URL']) assert.equal(raw.includes(secret), false)
})
test('release readiness fails for failed, skipped, cancelled or missing prerequisites', () => {
  const needs = Object.fromEntries(pipeline.jobs['release-readiness'].needs.map(name => [name, { result: 'success' }]))
  const run = value => spawnSync(process.execPath, [new URL('../check-ci-readiness.mjs', import.meta.url).pathname], { env: { ...process.env, NEEDS_JSON: JSON.stringify(value) }, encoding: 'utf8' })
  assert.equal(run(needs).status, 0)
  for (const result of ['failure', 'skipped', 'cancelled']) {
    assert.notEqual(run({ ...needs, 'dependency-security': { result } }).status, 0)
  }
  assert.notEqual(run({ ...needs, future_check: { result: 'failure' } }).status, 0)
  for (const name of ['tests', 'scaleway-runtime']) {
    const incomplete = { ...needs }
    delete incomplete[name]
    assert.notEqual(run(incomplete).status, 0)
  }
})

test('resolves the actual Nuxt/Nitro/builders and site-config cycles to reviewed roots', () => {
  const report = JSON.parse(readFileSync(new URL('./fixtures/npm-audit-reviewed-cycles.json', import.meta.url)))
  const actualLock = { packages: Object.fromEntries(policy.exceptions.flatMap(entry => entry.nodes.map(node => [node, { version: entry.version }]))) }
  assert.equal(evaluateNpmAudit(report, actualLock, policy, '2026-10-23').acceptedAdvisories.length, 2)
  report.vulnerabilities.nuxt.via.push({ name: 'nuxt', url: 'https://github.com/advisories/NEW', severity: 'critical' })
  assert.throws(() => evaluateNpmAudit(report, actualLock, policy, '2026-10-23'), /Unreviewed advisory/)
})

test('rejects the retired node-forge advisory instead of accepting its former exception', () => {
  const report = {
    auditReportVersion: 2,
    metadata: { vulnerabilities: { total: 2 } },
    vulnerabilities: {
      'node-forge': {
        severity: 'high',
        nodes: ['node_modules/node-forge'],
        via: [{ name: 'node-forge', severity: 'high', url: 'https://github.com/advisories/GHSA-86w9-cpqp-85rv' }],
      },
      nuxt: { severity: 'high', nodes: ['node_modules/nuxt'], via: ['node-forge'] },
    },
  }
  const vulnerableLock = { packages: { 'node_modules/node-forge': { version: '1.4.0' } } }
  assert.throws(() => evaluateNpmAudit(report, vulnerableLock, policy, '2026-10-27'), /Unreviewed affected package: node-forge/)
})

test('Amplify also audits both scopes before building, including direct cloud builds', () => {
  const amplify = parse(readFileSync(new URL('../../amplify.yml', import.meta.url), 'utf8'))
  const preBuild = amplify.frontend.phases.preBuild.commands
  assert.ok(preBuild.includes('node ../scripts/check-security-versions.mjs'))
  assert.ok(preBuild.includes('node ../scripts/audit-npm.mjs all npm-audit.json'))
  assert.ok(preBuild.includes('node ../scripts/audit-npm.mjs production npm-audit-production.json'))
})

test('CLI retains the raw nonzero scan, rejects a new upstream release and rejects registry failures', t => {
  const dir = mkdtempSync(join(tmpdir(), 'opnform-audit-'))
  t.after(() => rmSync(dir, { recursive: true, force: true }))
  mkdirSync(join(dir, 'bin'))
  const report = JSON.parse(readFileSync(new URL('./fixtures/npm-audit-reviewed-cycles.json', import.meta.url)))
  const actualLock = { packages: Object.fromEntries(policy.exceptions.flatMap(entry => entry.nodes.map(node => [node, { version: entry.version }]))) }
  writeFileSync(join(dir, 'package-lock.json'), JSON.stringify(actualLock))
  const versions = Object.fromEntries(policy.exceptions.map(entry => [entry.package, entry.upstreamLatest]))
  const installFakeNpm = (latest, failure = false) => {
    writeFileSync(join(dir, 'bin/npm'), '#!/usr/bin/env node\n' + `if (process.argv[2] === 'audit') { process.stdout.write(${JSON.stringify(JSON.stringify(report))}); process.exit(1) } else { process.stdout.write(JSON.stringify(${JSON.stringify(latest)}[process.argv[3]])); process.exit(${failure ? 1 : 0}) }`)
    chmodSync(join(dir, 'bin/npm'), 0o755)
  }
  const reviewedAt = policy.exceptions.map(entry => entry.reviewed).sort().at(-1)
  writeFileSync(join(dir, 'clock.cjs'), `const NativeDate = Date; globalThis.Date = class extends NativeDate { constructor(...args) { super(...(args.length ? args : [${JSON.stringify(reviewedAt + 'T12:00:00Z')}])); } };`)
  const run = () => spawnSync(process.execPath, ['--import', join(dir, 'clock.cjs'), new URL('../audit-npm.mjs', import.meta.url).pathname, 'all', 'raw.json'], { cwd: dir, env: { ...process.env, PATH: `${join(dir, 'bin')}:${process.env.PATH}` }, encoding: 'utf8' })
  installFakeNpm(versions)
  // Freeze only the child test process; the real CLI uses the current clock.
  assert.equal(run().status, 0)
  assert.deepEqual(JSON.parse(readFileSync(join(dir, 'raw.json'))), report)
  installFakeNpm({ ...versions, braces: '99.0.0' })
  assert.notEqual(run().status, 0)
  installFakeNpm(versions, true)
  assert.notEqual(run().status, 0)
})

test('legacy Amplify recovery configuration remains valid but regular CI does not build it', () => {
  const config = readFileSync(new URL('../../client/nuxt.config.ts', import.meta.url), 'utf8')
  assert.match(config, /awsAmplify: \{ runtime: 'nodejs24\.x' \}/)
  const amplify = parse(readFileSync(new URL('../../amplify.yml', import.meta.url), 'utf8'))
  assert.ok(amplify.frontend.phases.build.commands.includes('npm run build:amplify'))
  const pkg = JSON.parse(readFileSync(new URL('../../client/package.json', import.meta.url)))
  assert.match(pkg.scripts['build:amplify'], /NITRO_PRESET=aws-amplify npm run build/)
  assert.match(pkg.scripts['build:amplify'], /check-amplify-output\.mjs/)
  const smoke = pipeline.jobs.linux_client_image.steps.find(step => step.name === 'Smoke standalone Amplify compute on Linux Node 24')
  assert.equal(smoke, undefined)
  assert.equal(pipeline.jobs.client_checks.steps.some(step => step.run?.includes('npm run build:amplify')), false)
  assert.equal(pipeline.jobs.linux_client_image.steps.some(step => step.with?.name === 'amplify-output'), false)
  assert.ok(pipeline.jobs['release-readiness'].needs.includes('linux_client_image'))
})

test('image QA uses a valid JWT fixture and preserves server environment', () => {
  assert.equal(pipeline.jobs.linux_client_image.env.APP_ENV, 'e2e')
  assert.ok(pipeline.jobs.linux_client_image.env.JWT_SECRET.length >= 32)
  const preparation = pipeline.jobs.linux_client_image.steps.find(step => step.name === 'Prepare real API for image health checks').run
  assert.match(preparation, /artisan serve --no-reload/)
  assert.match(preparation, /passport:keys/)
  const evidence = pipeline.jobs.linux_client_image.steps.find(step => step.with?.name === 'linux-client-image-evidence')
  assert.match(evidence.with.path, /api\/storage\/logs\/laravel\.log/)
})
