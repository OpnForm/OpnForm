import { test } from 'node:test'
import assert from 'node:assert/strict'
import { eventLabel, eventColor, isStale, recipientLabel } from '../lib/integration-email-status.js'

test('acceptance and legacy success do not imply mailbox delivery', () => {
  assert.equal(eventLabel({ status: 'Accepted' }), 'Accepted · delivery unconfirmed')
  assert.equal(eventLabel({ status: 'Success', legacy_email: true }), 'Completed · delivery untracked')
  assert.equal(eventColor({ status: 'Success', legacy_email: true }), 'neutral')
  assert.equal(eventLabel({ status: 'Success' }), 'Success')
})

test('abandoned processing becomes explicitly uncertain', () => {
  const event = { email_tracking: true, status: 'Processing', updated_at: '2026-01-01T00:00:00Z' }
  assert.equal(isStale(event, Date.parse('2026-01-01T00:11:00Z')), true)
  assert.equal(isStale(event, Date.parse('2026-01-01T00:01:00Z')), false)
  assert.equal(isStale({ ...event, email_tracking: false }, Date.now()), false)
})

test('a partial failure does not conceal an abandoned recipient', () => {
  const event = { email_tracking: true, status: 'Error', data: { email: { recipients: { a: { status: 'sending', updated_at: '2026-01-01T00:00:00Z' } } } } }
  assert.equal(isStale(event, Date.parse('2026-01-01T00:11:00Z')), true)
  assert.equal(recipientLabel('bounced'), 'Bounced')
  assert.equal(recipientLabel('unknown'), 'Outcome unknown')
})


test('renders recipient evidence and the inbox limitation in the actual Vue component', async () => {
  const { readFile, writeFile, mkdir, rm } = await import('node:fs/promises')
  const { pathToFileURL } = await import('node:url')
  const { resolve } = await import('node:path')
  const { parse, compileScript } = await import('@vue/compiler-sfc')
  const { createSSRApp } = await import('vue')
  const { renderToString } = await import('@vue/server-renderer')
  const source = await readFile(new URL('../components/open/integrations/components/IntegrationEmailEvent.vue', import.meta.url), 'utf8')
  const { descriptor } = parse(source)
  const compiled = compileScript(descriptor, { id: 'email-event-test', inlineTemplate: true })
  const generatedPath = resolve('node_modules/.cache/email-event-test.mjs')
  await mkdir(resolve('node_modules/.cache'), { recursive: true })
  await writeFile(generatedPath, compiled.content.replace('~/lib/integration-email-status', pathToFileURL(resolve('lib/integration-email-status.js')).href))
  try {
    const { default: component } = await import(pathToFileURL(generatedPath).href)
    const html = await renderToString(createSSRApp(component, { event: {
      email_tracking: true, status: 'Error', data: { email: { recipients: {
        first: { address: 'one@example.com', status: 'delivered', provider_message_id: 'ses-123' },
        second: { address: 'two@example.com', status: 'bounced', reason: 'Receiving server rejected the email.' },
      } } },
    } }))
    assert.match(html, /Delivered to mail server/)
    assert.match(html, /Bounced/)
    assert.match(html, /ses-123/)
    assert.match(html, /not inbox placement/)
  } finally {
    await rm(generatedPath)
  }
})
