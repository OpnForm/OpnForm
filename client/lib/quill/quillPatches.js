import Quill from 'quill'
import QuillResize from 'quill-resize-module'
import 'quill-resize-module/dist/resize.css'
  
Quill.register('modules/resize', QuillResize)
  
// Self-executing function to patch Quill's prototype
;(function installQuillFixes() {

  // ---------------------------------------------------------------------------
  // NBSP handling: preserve multiples spaces without blocking wrapping
  // ---------------------------------------------------------------------------

  const normaliseNbsp = (text) => {
    const placeholder = '\uF000'
    return text
      .replace(/\u00A0\u00A0/g, placeholder) // protect pairs
      .replace(/\u00A0/g, ' ')               // single NBSP → space
      .replace(new RegExp(placeholder, 'g'), '\u00A0 ') // pair → NBSP + space
  }

  // 2a) Tweak getSemanticHTML so exported HTML keeps blank lines **and**
  //     converts NBSP-runs into NBSP+space sequences.
  const originalGetSemanticHTML = Quill.prototype.getSemanticHTML
  Quill.prototype.getSemanticHTML = function (index = 0, length) {
    let html = originalGetSemanticHTML.call(this, index, length || this.getLength())

    // Fix blank lines (<p></p> → <p><br/></p>)
    html = html.replace(/<p><\/p>/g, '<p><br/></p>')

    // NBSP entity form
    html = html.replace(/&nbsp;&nbsp;/g, '&nbsp; ').replace(/&nbsp;/g, ' ')

    // Raw NBSP char (just in case)
    html = normaliseNbsp(html)

    return html
  }

  // 2b) Clipboard matcher so pasted content is normalised before entering Delta
  if (typeof window !== 'undefined') {
    const Clipboard = Quill.import('modules/clipboard')
    const Delta     = Quill.import('delta')

    const matcher = (node) => {
      const text = normaliseNbsp(node.data)
      return new Delta().insert(text)
    }

    Clipboard.DEFAULTS.matchers.push([Node.TEXT_NODE, matcher])

    // 2c) Preserve quill-resize-module alignment (ql-resize-style-*) when pasting HTML
    const resizeAlignments = ['left', 'right', 'center', 'full']
    const getResizeAlignment = (el) => {
      if (!el || !el.classList) return null
      for (const align of resizeAlignments) {
        if (el.classList.contains(`ql-resize-style-${align}`)) return align
      }
      return null
    }

    const imgMatcher = (node, delta) => {
      const align = getResizeAlignment(node) || getResizeAlignment(node.parentElement)
      if (align && delta && delta.ops && delta.ops[0]) {
        const attrs = delta.ops[0].attributes || {}
        if (delta.ops[0].insert && typeof delta.ops[0].insert === 'object' && delta.ops[0].insert.image) {
          attrs['resize-inline'] = align
          delta.ops[0].attributes = attrs
        }
      }
      return delta
    }

    const blockMatcher = (node, delta) => {
      const align = getResizeAlignment(node)
      if (align && delta && delta.ops) {
        for (const op of delta.ops) {
          if (op.insert === '\n' || (typeof op.insert === 'string' && op.insert.includes('\n'))) {
            op.attributes = op.attributes || {}
            op.attributes['resize-block'] = align
            break
          }
        }
      }
      return delta
    }

    Clipboard.DEFAULTS.matchers.push(['IMG', imgMatcher])
    Clipboard.DEFAULTS.matchers.push(['P', blockMatcher])
    Clipboard.DEFAULTS.matchers.push(['DIV', blockMatcher])
  }
})()

export default {} 