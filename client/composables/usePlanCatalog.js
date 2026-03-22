export function usePlanCatalog() {
  const catalog = useState('planCatalog', () => ({ tiers: {} }))
  const tiers = computed(() => catalog.value?.tiers ?? {})

  return {
    catalog,
    tiers,
  }
}
