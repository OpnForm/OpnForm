import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { JSDOM } from 'jsdom'

const embedSources = [
  ['source', readFileSync(resolve(__dirname, '../../public/widgets/embed.js'), 'utf8')],
  ['minified', readFileSync(resolve(__dirname, '../../public/widgets/embed-min.js'), 'utf8')],
] as const

describe.each(embedSources)('popup embed attribution (%s)', (_variant, embedSource) => {
  it('fills missing iframe attribution from the parent without forwarding arbitrary parameters', () => {
    const dom = new JSDOM('<!doctype html><html><head></head><body></body></html>', {
      url: 'https://embedder.example.test/?utm_source=parent&utm_campaign=launch&email=secret@example.test',
      runScripts: 'outside-only',
    })
    const script = dom.window.document.createElement('script')
    script.setAttribute('data-nf', JSON.stringify({
      formurl: 'https://forms.example.test/forms/demo?utm_source=iframe',
    }))
    Object.defineProperty(dom.window.document, 'currentScript', {
      configurable: true,
      value: script,
    })

    dom.window.eval(embedSource)
    const button = dom.window.document.querySelector('.nf-emoji') as HTMLElement
    button.click()

    const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement
    const iframeUrl = new URL(iframe.src)
    expect(iframeUrl.searchParams.get('utm_source')).toBe('iframe')
    expect(iframeUrl.searchParams.get('utm_campaign')).toBe('launch')
    expect(iframeUrl.searchParams.get('email')).toBeNull()
    expect(iframeUrl.searchParams.get('popup')).toBe('true')
  })

  it('falls back to valid parent attribution when the iframe value is invalid', () => {
    const dom = new JSDOM('<!doctype html><html><head></head><body></body></html>', {
      url: 'https://embedder.example.test/?utm_source=parent&utm_campaign=launch',
      runScripts: 'outside-only',
    })
    const script = dom.window.document.createElement('script')
    script.setAttribute('data-nf', JSON.stringify({
      formurl: `https://forms.example.test/forms/demo?utm_source=&utm_campaign=${'x'.repeat(2049)}`,
    }))
    Object.defineProperty(dom.window.document, 'currentScript', {
      configurable: true,
      value: script,
    })

    dom.window.eval(embedSource)
    const button = dom.window.document.querySelector('.nf-emoji') as HTMLElement
    button.click()

    const iframe = dom.window.document.querySelector('iframe') as HTMLIFrameElement
    const iframeUrl = new URL(iframe.src)
    expect(iframeUrl.searchParams.get('utm_source')).toBe('parent')
    expect(iframeUrl.searchParams.get('utm_campaign')).toBe('launch')
  })
})
