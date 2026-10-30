// Keep in sync with the backend FormDateValue parser. Never coerce empty values.
export function dateTimestamp(value) {
  if (typeof value !== 'string') return null
  const parts = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,6}))?)?(Z|[+-]\d{2}:\d{2})?)?$/.exec(value)
  if (!parts || parts[0] !== value) return null
  const [, y, m, d, h = '0', min = '0', s = '0', fraction = '', zone = 'Z'] = parts
  const [year, month, day, hour, minute, second] = [y, m, d, h, min, s].map(Number)
  if (year < 1 || month < 1 || month > 12 || day < 1 || hour > 23 || minute > 59 || second > 59) return null
  const date = new Date(0)
  date.setUTCFullYear(year, month - 1, day)
  date.setUTCHours(hour, minute, second, Number(fraction.slice(0, 3).padEnd(3, '0')))
  if (date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null
  let offset = 0
  if (zone !== 'Z') {
    const zoneHour = Number(zone.slice(1, 3))
    const zoneMinute = Number(zone.slice(4, 6))
    if (zoneHour > 23 || zoneMinute > 59) return null
    offset = (zoneHour * 60 + zoneMinute) * (zone[0] === '-' ? -1 : 1)
  }
  return date.getTime() - offset * 60000
}

export function compareDates(operator, left, right, withTime = true) {
  let a = dateTimestamp(left)
  let b = dateTimestamp(right)
  if (!withTime && a !== null && b !== null) {
    a = dateTimestamp(left.slice(0, 10))
    b = dateTimestamp(right.slice(0, 10))
  }
  if (a === null || b === null) return false
  switch (operator) {
    case 'equals': return a === b
    case 'before': return a < b
    case 'after': return a > b
    case 'on_or_before': return a <= b
    case 'on_or_after': return a >= b
    default: return false
  }
}
