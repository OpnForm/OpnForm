import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'

const template = readFileSync(new URL('../../api/resources/views/mcp/form-draft-preview-app.blade.php', import.meta.url), 'utf8')
const script = template.match(/<script type="module">([\s\S]*?)<\/script>/)[1]

async function widget() {
  const elements = new Map()
  const element = () => ({
    textContent: '', style: {}, clientWidth: 500, hidden: true,
    replaceChildren() {}, appendChild() {},
  })
  const get = id => {
    if (!elements.has(id)) elements.set(id, element())
    return elements.get(id)
  }
  const calls = []
  let render
  let input
  let ready
  vm.runInNewContext(script, {
    URL,
    ResizeObserver: class { observe() {} },
    document: {
      getElementById: get,
      createElement: element,
      head: { appendChild(script) { script.onload() } },
    },
    window: { setTimeout() { return 1 }, clearTimeout() {}, iFrameResize() {} },
    createMcpApp(setup) {
      ready = setup({
        onToolInput(handler) { input = handler },
        onToolResult(handler) { render = handler },
        autoResize() {},
        async callServerTool(name, args) {
          calls.push({ name, ...args })
          return { _meta: { editor_url: 'https://opnform.test/editor' } }
        },
        async openLink() {},
      })
    },
  })
  await ready
  return { get, render, input, calls }
}

function result(version, handle = 'draft-a') {
  return {
    structuredContent: {
      draft_handle: handle,
      draft: { version, definition: { title: `Contact v${version}`, properties: [] } },
    },
    _meta: { preview_url: `https://opnform.test/agent-drafts/preview?version=${version}` },
  }
}

test('each widget stays on its initial revision, including late host results', async () => {
  const first = await widget()
  const second = await widget()
  first.render(result(1))
  const originalUrl = first.get('preview').src
  second.render(result(2))
  assert.equal(first.render(result(2)), false)
  assert.equal(first.render(result(1, 'another-draft')), false)
  assert.equal(first.get('title').textContent, 'Contact v1')
  assert.equal(first.get('preview').src, originalUrl)
  assert.equal(second.get('title').textContent, 'Contact v2')
  assert.match(first.get('version-hint').textContent, /return to v1/)
})

test('editor action sends the displayed revision, not another host result', async () => {
  const preview = await widget()
  preview.render(result(1))
  preview.render(result(2))
  await preview.get('open-editor').onclick()
  assert.equal(preview.calls.length, 1)
  assert.equal(preview.calls[0].name, 'open_form_draft_in_editor')
  assert.equal(preview.calls[0].expected_version, 1)
  assert.equal(preview.calls[0].draft_handle, 'draft-a')
})

test('editor action waits for a complete versioned result', async () => {
  const preview = await widget()
  preview.input({ arguments: { draft_handle: 'draft-a' } })
  assert.equal(preview.get('open-editor').disabled, true)
  await preview.get('open-editor').onclick()
  assert.equal(preview.calls.length, 0)

  preview.render(result(1))
  assert.equal(preview.get('open-editor').disabled, false)
  await preview.get('open-editor').onclick()
  assert.equal(preview.calls.length, 1)
  assert.equal(preview.calls[0].expected_version, 1)
})

test('late tool input cannot retarget the editor action of a rendered preview', async () => {
  const preview = await widget()
  preview.render(result(1))
  preview.input({ arguments: { draft_handle: 'another-draft' } })
  await preview.get('open-editor').onclick()
  assert.equal(preview.calls[0].draft_handle, 'draft-a')
  assert.equal(preview.calls[0].expected_version, 1)
})

test('an incomplete host result does not prevent the full initial preview loading', async () => {
  const preview = await widget()
  preview.render({ _meta: { preview_url: 'https://opnform.test/preview' } })
  preview.render(result(1))
  assert.equal(preview.get('title').textContent, 'Contact v1')
  assert.match(preview.get('preview').src, /version=1/)
})
