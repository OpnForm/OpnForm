import { toValue } from "vue"

export function serializeJsonLd(schema) {
  return JSON.stringify(schema)
    .replace(/</g, "\\u003C")
    .replace(/>/g, "\\u003E")
    .replace(/&/g, "\\u0026")
    .replace(/\u2028/g, "\\u2028")
    .replace(/\u2029/g, "\\u2029")
}

export function useJsonLd(key, schema) {
  return useHead(() => {
    const value = toValue(schema)
    const schemas = (Array.isArray(value) ? value : [value]).filter(Boolean)

    if (schemas.length === 0) {
      return {}
    }

    return {
      script: schemas.map((item, index) => ({
        key: schemas.length === 1 ? key : `${key}-${index}`,
        type: "application/ld+json",
        textContent: serializeJsonLd(item),
      })),
    }
  })
}
