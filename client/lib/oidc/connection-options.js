export const getOidcRequireStateDefault = () => true

export const getOidcRequireStateForEdit = (options) => {
  return options?.require_state ?? false
}
