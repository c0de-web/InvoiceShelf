<template>
  <div>
    <div class="flex h-2.5 overflow-hidden bg-gray-100 rounded-full">
      <div class="bg-primary-600 transition-all" :style="{ width: `${paid}%` }" />
      <div class="bg-primary-200 transition-all" :style="{ width: `${invoicedUnpaid}%` }" />
    </div>
    <div v-if="showLegend" class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
      <span class="inline-flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-sm bg-primary-600" />
        {{ $t('engagements.paid') }} {{ paid }}%
      </span>
      <span class="inline-flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-sm bg-primary-200" />
        {{ $t('engagements.invoiced_unpaid') }} {{ invoicedUnpaid }}%
      </span>
      <span class="inline-flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-sm bg-gray-200" />
        {{ $t('engagements.not_invoiced') }} {{ 100 - paid - invoicedUnpaid }}%
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  summary: {
    type: Object,
    required: true,
  },
  showLegend: {
    type: Boolean,
    default: false,
  },
})

const clamp = (value) => Math.min(Math.max(value || 0, 0), 100)

const paid = computed(() => clamp(props.summary.paid_percent))
const invoicedUnpaid = computed(() =>
  clamp(clamp(props.summary.invoiced_percent) - paid.value)
)
</script>
