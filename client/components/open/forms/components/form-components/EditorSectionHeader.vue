<template>
  <div class="relative">
    <hr
      v-if="showLine"
      class="border-t border-neutral-200 mb-6 mt-8 -mx-4"
    >
    <div
      :class="[
        'flex gap-x-2 items-center',
        showLine ? 'mb-2 px-2 -mx-2 absolute -top-[10px] bg-white' : 'my-4'
      ]"
    >
      <Icon
        :name="icon"
        :class="['w-4 h-4 text-neutral-400', iconClass]"
      />
      <h3 class="text-sm font-semibold text-neutral-500">
        {{ title }}
      </h3>
    </div>
  </div>
</template>
  
<script setup>
defineProps({
icon: {
    type: String,
    required: true
},
title: {
    type: String,
    required: true
},
showLine: {
    type: Boolean,
    default: true
},
iconClass: {
    type: String,
    default: ''
}
})
</script>