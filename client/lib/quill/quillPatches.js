import Quill from 'quill'

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
  }

  // ---------------------------------------------------------------------------
  // Tab key accessibility fix: allow Tab to navigate out of editor
  // ---------------------------------------------------------------------------
  
  const Keyboard = Quill.import('modules/keyboard')
  
  // Override Tab key behavior to allow navigation instead of inserting tabs
  Keyboard.DEFAULTS.bindings.tab = {
    key: 'Tab',
    handler: () => true // Return true to let browser handle Tab navigation
  }
})()

export default {} 