/**
 * Initialize vendor clients from the current user only after authenticated app
 * bootstrap has completed.
 */
export const initServiceClients = (userData) => {
  if (import.meta.server || !userData) return

  useAmplitude().setUser(userData)
  useCrisp().setUser(userData)
}
