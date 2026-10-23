import assert from 'node:assert/strict'
import { writeFileSync } from 'node:fs'
const ui = process.env.IMAGE_QA_UI || 'http://127.0.0.1:3100'
const api = process.env.IMAGE_QA_API || 'http://127.0.0.1:8089'
async function read(url, options) {
  const response = await fetch(url, { ...options, signal: AbortSignal.timeout(10000) })
  assert.equal(response.status, 200, `${url} returns HTTP 200`)
  return response
}
for (let attempt = 0; ; attempt++) {
  try { await read(`${ui}/login`); break } catch (error) {
    if (attempt === 29) throw error
    await new Promise(resolve => setTimeout(resolve, 1000))
  }
}
for (const page of ['login', 'register']) {
  assert.match(await (await read(`${ui}/${page}`)).text(), new RegExp(`data-testid="${page}-page"`))
}
const { token } = await (await read(`${api}/login`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: 'e2e@example.test', password: 'Abcd@1234' }) })).json()
assert.ok(token)
const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
const workspaces = await (await read(`${api}/open/workspaces`, { headers })).json()
const forms = await (await read(`${api}/open/workspaces/${workspaces[0].id}/forms?per_page=100`, { headers })).json()
const form = (forms.data || forms).find(form => form.title === 'Product feedback')
assert.ok(form?.slug, 'Seeded public form available through authenticated API')
const html = await (await read(`${ui}/forms/${form.slug}`)).text()
assert.match(html, /Product feedback/)
assert.match(html, /Full name/)
assert.match(html, /open-complete-form/)
// Verify local JSON collections and a repository SVG icon on the real runtime.
for (const [collection, icon] of [['heroicons', 'check'], ['lucide', 'link'], ['opnform', 'fillout']]) {
  const icons = await (await read(`${ui}/_nuxt_icon/${collection}.json?icons=${icon}`)).json()
  assert.ok(icons.icons?.[icon]?.body, `Local ${collection}:${icon} icon is served`)
}
writeFileSync(process.env.IMAGE_QA_OUTPUT || 'client-image-health.json', JSON.stringify({ testedSha: process.env.GITHUB_SHA || null, headSha: process.env.SOURCE_HEAD_SHA || null, ui, api, login: true, register: true, authenticatedApi: true, publicFormSSR: true, localIcons: true }, null, 2)+'\n')
console.log('Frontend health passed: auth SSR, authenticated API and public form SSR and local icons.')
