<template>
  <div>
    <UPopover v-model:open="isPopoverOpen" arrow :content="popoverContent">
      <UTooltip text="Add question with AI" arrow>
        <UButton
          size="sm"
          color="neutral"
          variant="outline"
          icon="i-heroicons-sparkles"
          :loading="loading"
        />
      </UTooltip>

      <template #content>
        <div class="p-4 w-72">
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium">Add new field(s) with AI</p>
            </div>

            <p class="text-xs text-neutral-500">
              This will generate new fields based on your description.
            </p>

            <TextAreaInput
              name="fields_prompt" 
              :disabled="loading" 
              :form="aiFields"
              placeholder="Describe the question you want to add with AI..."
            />

            <UButton
              class="mt-2"
              icon="i-heroicons-sparkles"
              label="Generate"
              block
              :loading="loading"
              @click="handleGenerate"
            />
          </div>
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script setup>
import { formsApi } from '~/api'

defineProps({
  popoverContent: {
    type: Object,
    default: () => ({
      align: 'start',
      side: 'left',
    }),
  },
})

const workingFormStore = useWorkingFormStore()
const { content: form } = storeToRefs(workingFormStore)

const isPopoverOpen = ref(false)
const loading = ref(false)
const aiRequestId = ref(null)
const aiFields = useForm({
  fields_prompt: '',
  current_form_structure: null
})

onMounted(() => {
  if(form.value) {
    aiFields.current_form_structure = {
        'title': form.value.title,
        'properties': form.value.properties.map(property => ({
          'name': property.name,
          'type': property.type
        }))
      }
  }
})

const handleGenerate = () => {
  if (loading.value) return

  if (!aiFields.fields_prompt) {
    useAlert().warning('Please describe the fields you want to add with AI.')
    return
  }
    
  loading.value = true
  aiRequestId.value = null
  const presentationStyle = form.value?.presentation_style || 'classic'
  formsApi.ai.generateFields({
    ...aiFields.data(),
    generation_params: { presentation_style: presentationStyle }
  }).then(data => {
    aiRequestId.value = data.ai_form_completion_id
    fetchGeneratedForm(data.ai_form_completion_id)
  }).catch(error => {
    console.error('Failed to add new field(s):', error)
    useAlert().error(error.response?.data?.message ?? 'Failed to add new field(s).')
    loading.value = false
  })
}

const fetchGeneratedForm = (generationId) => {
  // If aiRequestId is null, it means we cancelled the request
  if (!aiRequestId.value) {
    loading.value = false
    return
  }

  const checkFormStatus = () => {
    // If aiRequestId is null, it means we cancelled the request
    if (!aiRequestId.value) {
      loading.value = false
      return
    }

    formsApi.ai.get(generationId).then(data => {
      if (data.ai_form_completion.status === 'completed') {
        // Only proceed if we haven't cancelled
        if (aiRequestId.value) {
          workingFormStore.addGeneratedFields(JSON.parse(data.ai_form_completion.result))
        }
        loading.value = false
        isPopoverOpen.value = false
        useAlert().success('New field(s) added successfully.')
        aiFields.fields_prompt = ''
      } else if (data.ai_form_completion.status === 'failed') {
        useAlert().error('Something went wrong, please try again.')
        loading.value = false
      } else {
        // Call itself again after 4 seconds if form is not yet ready
        setTimeout(checkFormStatus, 4000)
      }
    }).catch(error => {
      console.error(error)
      useAlert().error(error.response?.data?.message)
      loading.value = false
    })
  }

  // Call the function immediately
  checkFormStatus()
}
</script> 