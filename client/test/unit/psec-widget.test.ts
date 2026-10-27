import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { JSDOM } from 'jsdom'

describe('popup widget configuration', () => {
  it.each(['embed.js', 'embed-min.js'])('%s renders an untrusted emoji as text without creating executable HTML', file => {
    const source = readFileSync(new URL('../../public/widgets/' + file, import.meta.url), 'utf8')
    const dom = new JSDOM('<body></body>', { runScripts: 'dangerously', url: 'https://website.example.test/' })
    const payload = '<img src=x onerror="window.compromised=true">'
    const script = dom.window.document.createElement('script')
    script.setAttribute('data-nf', JSON.stringify({ formurl: 'https://forms.example.test/form', emoji: payload }))
    script.textContent = source
    dom.window.document.body.appendChild(script)
    expect(dom.window.document.querySelector('.nf-emoji-icon')?.textContent).toBe(payload)
    expect(dom.window.document.querySelector('.nf-emoji img')).toBeNull()
    expect(dom.window.compromised).toBeUndefined()
    dom.window.close()
  })
})
