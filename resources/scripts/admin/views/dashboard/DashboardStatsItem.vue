<template>
  <router-link
    v-if="!loading"
    class="
      relative
      flex
      justify-between
      p-4
      bg-white
      border border-gray-100
      rounded-2xl
      shadow-sm
      transition-shadow
      hover:shadow-md
      xl:p-5
      lg:col-span-2
    "
    :class="{ 'lg:!col-span-3': large }"
    :to="route"
  >
    <div>
      <span class="text-xl font-semibold leading-tight text-black xl:text-3xl">
        <slot />
      </span>
      <span class="block mt-1 text-sm leading-tight text-gray-500 xl:text-lg">
        {{ label }}
      </span>
    </div>
    <div
      class="flex items-center justify-center w-11 h-11 rounded-xl bg-primary-50 text-primary-600 xl:w-12 xl:h-12"
    >
      <component :is="iconComponent" class="w-6 h-6" :stroke-width="1.75" />
    </div>
  </router-link>

  <StatsCardPlaceholder v-else-if="large" />

  <StatsCardSmPlaceholder v-else />
</template>


<script setup>
import StatsCardPlaceholder from './DashboardStatsPlaceholder.vue'
import StatsCardSmPlaceholder from './DashboardStatsSmPlaceholder.vue'

defineProps({
  iconComponent: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  route: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  large: {
    type: Boolean,
    default: false,
  },
})
</script>
