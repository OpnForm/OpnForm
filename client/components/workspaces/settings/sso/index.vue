<template>
  <div class="space-y-8">
    <Oidc />
    <div class="border-t border-neutral-200"></div>
    <Saml />
  </div>
</template>

<script setup>
import Oidc from './Oidc.vue'
import Saml from './Saml.vue'
</script>

