<template>
  <div class="space-y-4">
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-medium text-neutral-900">SAML Settings</h3>
          <UBadge color="neutral" variant="subtle">
            Not available yet
          </UBadge>
        </div>
        <p class="mt-1 text-sm text-neutral-500">
          SAML single sign-on is not available yet. Use OIDC for workspace SSO.
        </p>
      </div>
    </div>

    <!-- Empty State -->
    <div class="text-center py-12">
      <UIcon 
        name="i-heroicons-shield-check" 
        class="w-12 h-12 text-neutral-400 mx-auto mb-4" 
      />
      <h4 class="text-lg font-medium text-neutral-900 mb-2">
        No SAML connections yet
      </h4>
      <p class="text-neutral-500 mb-6 max-w-md mx-auto">
        SAML setup is not implemented in this version of OpnForm.
      </p>
    </div>
  </div>
</template>
