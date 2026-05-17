<script setup>

definePageMeta({
  middleware: 'auth'
})

onMounted(() => {
  useAlert().error('Checkout was canceled or could not be confirmed.')
  navigateTo({ name: 'pricing' })
})
</script>
