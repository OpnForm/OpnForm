import assert from 'node:assert/strict'
const needs = JSON.parse(process.env.NEEDS_JSON || '{}')
for (const name of ['code_lint', 'client_checks', 'sync_check', 'tests', 'database_compatibility', 'e2e', 'linux_client_image', 'dependency-security']) {
  assert.ok(Object.hasOwn(needs, name), `Missing release prerequisite: ${name}`)
}
for (const [name, job] of Object.entries(needs)) {
  assert.equal(job.result, 'success', `${name} must succeed before release`)
}
console.log('All security and QA prerequisites passed.')
