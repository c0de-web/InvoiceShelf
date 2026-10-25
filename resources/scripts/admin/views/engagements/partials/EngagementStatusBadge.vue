<template>
  <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="styles[status]">
    <span class="w-1.5 h-1.5 rounded-full bg-current" />
    {{ $t(`engagements.statuses.${status}`) }}
  </span>
</template>

<script setup>
defineProps({
  status: {
    type: String,
    required: true,
  },
})

const styles = {
  ACTIVE: 'text-green-600',
  ON_HOLD: 'text-amber-600',
  COMPLETED: 'text-gray-500',
  CANCELLED: 'text-red-500',
}
</script>
