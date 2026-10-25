<template>
  <BasePage>
    <BasePageHeader :title="$t('engagements.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('engagements.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <router-link
          v-if="userStore.hasAbilities(abilities.CREATE_INVOICE)"
          to="/admin/engagements/create"
        >
          <BaseButton variant="primary">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('engagements.new') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-6">
      <div class="inline-flex p-1 bg-white border border-gray-100 rounded-xl shadow-sm">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          class="px-4 py-1.5 text-sm rounded-lg transition-colors"
          :class="
            activeType === tab.value
              ? 'bg-primary-50 text-primary-700 font-medium'
              : 'text-gray-500 hover:text-gray-800'
          "
          @click="activeType = tab.value"
        >
          {{ tab.label }}
        </button>
      </div>

      <BaseInput
        v-model="search"
        class="w-full sm:w-72"
        :placeholder="$t('engagements.search')"
      >
        <template #left="slotProps">
          <BaseIcon name="MagnifyingGlassIcon" :class="slotProps.class" />
        </template>
      </BaseInput>
    </div>

    <div v-if="isLoading" class="mt-16 text-center text-gray-400">
      {{ $t('engagements.loading') }}
    </div>

    <div
      v-else-if="!engagementStore.engagements.length"
      class="flex flex-col items-center py-20 mt-6 text-center bg-white border border-gray-100 rounded-2xl"
    >
      <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-primary-50 text-primary-600">
        <BaseIcon name="BriefcaseIcon" class="w-7 h-7" />
      </div>
      <p class="mt-4 text-lg font-medium text-gray-900">
        {{ $t('engagements.empty_title') }}
      </p>
      <p class="max-w-md mt-1 text-sm text-gray-500">
        {{ $t('engagements.empty_description') }}
      </p>
      <router-link to="/admin/engagements/create" class="mt-6">
        <BaseButton variant="primary-outline">
          {{ $t('engagements.new') }}
        </BaseButton>
      </router-link>
    </div>

    <div v-else class="grid gap-5 mt-6 md:grid-cols-2 xl:grid-cols-3">
      <router-link
        v-for="engagement in engagementStore.engagements"
        :key="engagement.id"
        :to="`/admin/engagements/${engagement.id}/view`"
        class="flex flex-col p-5 bg-white border border-gray-100 rounded-2xl shadow-sm transition-shadow hover:shadow-md"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="font-semibold text-gray-900 truncate">{{ engagement.name }}</p>
            <p class="text-sm text-gray-500 truncate">{{ engagement.customer.name }}</p>
          </div>
          <EngagementTypeBadge :type="engagement.type" />
        </div>

        <template v-if="engagement.type === 'PROJECT'">
          <EngagementProgress class="mt-5" :summary="engagement.summary" />
          <div class="flex justify-between mt-3 text-sm">
            <span class="text-gray-500">
              {{ $t('engagements.paid_of', { percent: engagement.summary.paid_percent }) }}
            </span>
            <span class="text-gray-900">
              <BaseFormatMoney
                :amount="engagement.summary.contract"
                :currency="currencyOf(engagement)"
              />
            </span>
          </div>
          <p class="mt-1 text-sm text-gray-500">
            {{ $t('engagements.pending') }}:
            <BaseFormatMoney
              class="font-medium text-gray-900"
              :amount="engagement.summary.pending"
              :currency="currencyOf(engagement)"
            />
          </p>
        </template>

        <template v-else>
          <div class="flex justify-between mt-5 text-sm">
            <span class="text-gray-500">{{ $t('engagements.invoiced') }}</span>
            <BaseFormatMoney
              class="text-gray-900"
              :amount="engagement.summary.invoiced"
              :currency="currencyOf(engagement)"
            />
          </div>
          <div class="flex justify-between mt-1 text-sm">
            <span class="text-gray-500">{{ $t('engagements.outstanding') }}</span>
            <BaseFormatMoney
              class="font-medium text-gray-900"
              :amount="engagement.summary.pending"
              :currency="currencyOf(engagement)"
            />
          </div>
          <p class="mt-1 text-xs text-gray-400">
            {{ $t('engagements.recurring_count', engagement.recurring_invoices.length) }}
          </p>
        </template>

        <div class="flex items-center justify-between pt-4 mt-auto">
          <EngagementStatusBadge :status="engagement.status" />
          <BaseIcon name="ChevronRightIcon" class="w-4 h-4 text-gray-300" />
        </div>
      </router-link>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { debounce } from 'lodash'
import { useEngagementStore } from '@/scripts/admin/stores/engagement'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'
import EngagementTypeBadge from './partials/EngagementTypeBadge.vue'
import EngagementStatusBadge from './partials/EngagementStatusBadge.vue'
import EngagementProgress from './partials/EngagementProgress.vue'

const engagementStore = useEngagementStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const { t } = useI18n()

const activeType = ref('')
const search = ref('')
const isLoading = ref(true)

const tabs = computed(() => [
  { value: '', label: t('engagements.all') },
  { value: 'PROJECT', label: t('engagements.types.PROJECT', 2) },
  { value: 'RETAINER', label: t('engagements.types.RETAINER', 2) },
  { value: 'SERVICE', label: t('engagements.types.SERVICE', 2) },
])

function currencyOf(engagement) {
  return engagement.customer.currency || companyStore.selectedCompanyCurrency
}

async function load() {
  isLoading.value = true
  try {
    await engagementStore.fetchEngagements({ type: activeType.value, search: search.value })
  } finally {
    isLoading.value = false
  }
}

watch(activeType, load)
watch(search, debounce(load, 400))
load()
</script>
