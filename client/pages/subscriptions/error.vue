<script setup>
import { useBroadcastChannel } from '@vueuse/core'

definePageMeta({
  middleware: 'auth'
})

const subscribeBroadcast = useBroadcastChannel('subscribe')

onMounted(() => {
  subscribeBroadcast.post({ 'type': 'error' })
  window.close()
})
</script>