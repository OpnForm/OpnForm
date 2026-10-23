import { describe, expect, it } from 'vitest'
import Quill from 'quill'
import katex from 'katex'
import '~/lib/quill/quillPatches'
import registerMentionExtension from '~/lib/quill/quillMentionExtension'

function editor(formats) {
  const container = document.createElement('div')
  document.body.append(container)
  return new Quill(container, { formats })
}

describe('Quill HTML export security boundary', () => {
  it('sanitizes a formula export using the real Quill exporter', () => {
    window.katex = katex
    const quill = editor(['formula'])
    quill.setContents([{ insert: { formula: '<img src=x onerror="window.pwned=1">' } }])
    const html = quill.getSemanticHTML()
    const target = document.createElement('div')
    target.innerHTML = html
    expect(target.querySelector('[onerror]')).toBeNull()
    expect(target.innerHTML).not.toContain('window.pwned')
  })

  it('removes event attributes injected through a video export', () => {
    const quill = editor(['video'])
    quill.setContents([{ insert: { video: 'https://example.test/" onmouseover="window.pwned=1' } }])
    const target = document.createElement('div')
    target.innerHTML = quill.getSemanticHTML()
    expect(target.querySelector('a')).not.toBeNull()
    expect(target.querySelector('[onmouseover]')).toBeNull()
  })

  it('retains mention attributes and escaped literal HTML across exports', () => {
    registerMentionExtension(Quill)
    const quill = editor(['mention', 'bold'])
    quill.setContents([
      { insert: { mention: { field: { id: 'field-id', name: 'Name' }, fallback: 'Friend' } } },
      { insert: ' <img src=x onerror=alert(1)> & literal ', attributes: { bold: true } },
      { insert: '\n' },
    ])
    const html = quill.getSemanticHTML()
    const target = document.createElement('div')
    target.innerHTML = html
    expect(target.querySelector('[mention]')?.getAttribute('mention-field-id')).toBe('field-id')
    expect(target.querySelector('[mention]')?.getAttribute('mention-fallback')).toBe('Friend')
    expect(target.querySelector('img')).toBeNull()
    expect(target.textContent).toContain('<img src=x onerror=alert(1)> & literal')
    expect(target.querySelector('strong')).not.toBeNull()
  })
})
