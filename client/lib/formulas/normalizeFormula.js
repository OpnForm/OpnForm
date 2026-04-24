export function normalizeFormula(formula) {
  if (!formula) return ''

  let result = ''
  let inString = false
  let quoteChar = ''
  let lastWasSpace = false

  for (let i = 0; i < formula.length; i++) {
    const char = formula[i]

    if (inString) {
      result += char
      if (char === quoteChar) {
        inString = false
        quoteChar = ''
      }
      continue
    }

    if (char === '"' || char === "'") {
      inString = true
      quoteChar = char
      result += char
      lastWasSpace = false
      continue
    }

    if (/\s/.test(char)) {
      if (!lastWasSpace && result.length > 0) {
        result += ' '
        lastWasSpace = true
      }
      continue
    }

    result += char
    lastWasSpace = false
  }

  return result.trim()
}
