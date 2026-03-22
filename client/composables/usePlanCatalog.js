const DEFAULT_PLAN_CATALOG = {
  tiers: {
    free: { order: 0, name: 'Free', price_monthly: 0, price_yearly_per_month: 0 },
    pro: { order: 1, name: 'Pro', price_monthly: 29, price_yearly_per_month: 25 },
    business: { order: 2, name: 'Business', price_monthly: 79, price_yearly_per_month: 67 },
    enterprise: { order: 3, name: 'Enterprise', price_monthly: 250, price_yearly_per_month: 220 },
  },
}

export function usePlanCatalog() {
  const catalog = useState('planCatalog', () => DEFAULT_PLAN_CATALOG)
  const tiers = computed(() => catalog.value?.tiers ?? DEFAULT_PLAN_CATALOG.tiers)

  return {
    catalog,
    tiers,
  }
}
