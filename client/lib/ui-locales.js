export function resolveUiLocaleKey(locale, availableKeys) {
  const code = locale.toLowerCase().replaceAll('-', '_')
  const candidates = [code, code.split('_')[0]]
  const exact = candidates.find(candidate => availableKeys.includes(candidate))
  if (exact) return exact

  for (const candidate of candidates) {
    const regional = availableKeys.filter(key => key.startsWith(`${candidate}_`))
    if (regional.length === 1) return regional[0]
  }
  return 'en'
}
