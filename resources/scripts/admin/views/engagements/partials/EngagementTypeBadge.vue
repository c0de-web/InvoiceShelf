<template>
  <span
    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full shrink-0"
    :class="styles[type]"
  >
    <BaseIcon :name="icons[type]" class="w-3.5 h-3.5" />
    {{ $t(`engagements.types.${type}`, 1) }}
  </span>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    required: true,
  },
})

const icons = {
  PROJECT: 'BriefcaseIcon',
  RETAINER: 'ArrowPathIcon',
  SERVICE: 'CircleStackIcon',
}

const styles = {
  PROJECT: 'bg-primary-50 text-primary-700',
  RETAINER: 'bg-amber-50 text-amber-700',
  SERVICE: 'bg-sky-50 text-sky-700',
}
</script>
