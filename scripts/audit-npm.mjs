import assert from 'node:assert/strict'
import { readFileSync, writeFileSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { resolve } from 'node:path'

export function evaluateNpmAudit(report, lock, policy, today = new Date().toISOString().slice(0, 10)) {
  assert.ok(!report.error && report.auditReportVersion === 2 && report.metadata?.vulnerabilities, 'Invalid or failed npm audit response')
  assert.equal(Object.keys(report.vulnerabilities).length, report.metadata.vulnerabilities.total, 'Inconsistent audit counts')
  const roots = new Map()
  const rank = { info: 0, low: 1, moderate: 2, high: 3, critical: 4 }
  for (const [name, finding] of Object.entries(report.vulnerabilities)) {
    assert.ok(policy.affectedPackages.includes(name), `Unreviewed affected package: ${name}`)
    assert.ok(finding.via.length > 0, `Missing advisory provenance: ${name}`)
    roots.set(name, new Set())
    for (const via of finding.via) {
      if (typeof via === 'string') {
        assert.ok(report.vulnerabilities[via], `Missing advisory provenance: ${via}`)
        continue
      }
      const exception = policy.exceptions.find(entry => entry.package === via.name && entry.advisory === via.url)
      assert.ok(exception, `Unreviewed advisory: ${via.name} ${via.url}`)
      assert.ok(today <= exception.expires, `Expired security exception: ${via.name}`)
      assert.equal(via.severity, exception.severity, `Changed advisory severity: ${via.name}`)
      assert.ok(finding.nodes.length > 0, `Missing installed paths: ${name}`)
      for (const node of finding.nodes) {
        assert.ok(exception.nodes.includes(node), `Unreviewed advisory path: ${node}`)
        assert.equal(lock.packages[node]?.version, exception.version, `Unreviewed vulnerable version: ${node}`)
      }
      roots.get(name).add(exception.advisory)
    }
  }
  // npm legitimately reports cycles between Nuxt and its builders. Propagate
  // reviewed advisory roots to a fixed point; a rootless cycle still fails.
  let changed = true
  while (changed) {
    changed = false
    for (const [name, finding] of Object.entries(report.vulnerabilities)) {
      for (const via of finding.via.filter(value => typeof value === 'string')) {
        for (const advisory of roots.get(via)) {
          if (!roots.get(name).has(advisory)) { roots.get(name).add(advisory); changed = true }
        }
      }
    }
  }
  const accepted = new Set()
  for (const [name, advisories] of roots) {
    assert.ok(advisories.size > 0, `Missing reviewed advisory provenance (possibly rootless cycle): ${name}`)
    const severity = Math.max(...[...advisories].map(advisory => rank[policy.exceptions.find(entry => entry.advisory === advisory).severity]))
    assert.equal(rank[report.vulnerabilities[name].severity], severity, `Changed inherited severity: ${name}`)
    for (const advisory of advisories) accepted.add(advisory)
  }
  return { affectedPackages: roots.size, acceptedAdvisories: [...accepted], vulnerabilities: report.metadata.vulnerabilities }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const [scope, output = 'npm-audit.json'] = process.argv.slice(2)
  assert.ok(['all', 'production'].includes(scope), 'Expected scope all or production')
  const args = ['audit', '--package-lock-only', '--ignore-scripts', '--json', ...(scope === 'production' ? ['--omit=dev'] : [])]
  const scan = spawnSync(process.platform === 'win32' ? 'npm.cmd' : 'npm', args, { encoding: 'utf8' })
  writeFileSync(output, scan.stdout || '')
  assert.ok(!scan.error && [0, 1].includes(scan.status), `npm audit failed: ${scan.error || scan.stderr}`)
  const report = JSON.parse(scan.stdout)
  const lock = JSON.parse(readFileSync('package-lock.json', 'utf8'))
  const policy = JSON.parse(readFileSync(new URL('./npm-exceptions.json', import.meta.url), 'utf8'))
  const result = evaluateNpmAudit(report, lock, policy)
  assert.equal(scan.status, report.metadata.vulnerabilities.total > 0 ? 1 : 0, 'Unexpected raw scanner exit status')
  // npm's fixAvailable can mean a downgrade (including Quill 2.0.2), not
  // a patched release. Any change to the reviewed upstream latest needs review.
  for (const exception of policy.exceptions.filter(entry => result.acceptedAdvisories.includes(entry.advisory))) {
    const release = spawnSync(process.platform === 'win32' ? 'npm.cmd' : 'npm', ['view', exception.package, 'version', '--json'], { encoding: 'utf8' })
    assert.ok(!release.error && release.status === 0, `Failed upstream version lookup: ${exception.package}`)
    assert.equal(JSON.parse(release.stdout), exception.upstreamLatest, `New upstream release requires review: ${exception.package}`)
  }
  if (report.metadata.vulnerabilities.total > 0) {
    const message = `npm audit remains nonzero: ${result.affectedPackages} affected packages; only ${result.acceptedAdvisories.length} dated exceptions accepted. Raw JSON is retained; this is not a zero-vulnerability result.`
    console.warn(process.env.GITHUB_ACTIONS === 'true' ? `::warning::${message}` : message)
  }
  console.log(JSON.stringify({ rawScannerExit: scan.status, policy: 'passed', ...result }, null, 2))
}
