import { describe, expect, it } from 'vitest'
import Quill from 'quill'
import katex from 'katex'
import '~/lib/quill/quillPatches'
import registerMentionExtension from '~/lib/quill/quillMentionExtension'
import { sanitizeQuillHtml } from '~/lib/quill/sanitizeQuillHtml'

function editor(formats) {
  const container = document.createElement('div')
  document.body.append(container)
  return new Quill(container, { formats })
}

describe('Quill HTML export security boundary', () => {
  it('preserves safe new-tab links from the real Quill exporter', () => {
    const quill = editor(['link'])
    quill.setContents([
      { insert: 'Help', attributes: { link: 'https://example.test/help' } },
      { insert: '\n' },
    ])
    const target = document.createElement('div')
    target.innerHTML = quill.getSemanticHTML()
    const link = target.querySelector('a')
    expect(link?.getAttribute('href')).toBe('https://example.test/help')
    expect(link?.getAttribute('target')).toBe('_blank')
    expect(link?.getAttribute('rel')).toBe('noopener noreferrer')
  })

  it('drops arbitrary browsing targets and secures allowed new-tab links', () => {
    const target = document.createElement('div')
    target.innerHTML = sanitizeQuillHtml('<a href="https://example.test" target="_blank" rel="opener" onclick="alert(1)">safe</a><a href="https://example.test" target="_top">top</a><a href="https://example.test" target="named-window">named</a><a href="javascript:alert(1)" target="_blank">bad</a><form target="_top"><button>submit</button></form>')
    const links = target.querySelectorAll('a')
    expect(links[0].getAttribute('target')).toBe('_blank')
    expect(links[0].getAttribute('rel')).toBe('noopener noreferrer')
    expect(links[0].hasAttribute('onclick')).toBe(false)
    expect(links[1].hasAttribute('target')).toBe(false)
    expect(links[2].hasAttribute('target')).toBe(false)
    expect(links[3].hasAttribute('href')).toBe(false)
    expect(target.querySelector('form[target]')).toBeNull()
  })

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
