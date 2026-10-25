<template>
  <BasePage v-if="engagement">
    <BasePageHeader :title="engagement.name">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('engagements.title')" to="/admin/engagements" />
        <BaseBreadcrumbItem :title="engagement.name" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex gap-3">
          <BaseButton
            v-if="canDelete"
            variant="primary-outline"
            @click="remove"
          >
            {{ $t('general.delete') }}
          </BaseButton>
          <router-link
            v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
            :to="`/admin/engagements/${engagement.id}/edit`"
          >
            <BaseButton variant="primary">
              <template #left="slotProps">
                <BaseIcon name="PencilIcon" :class="slotProps.class" />
              </template>
              {{ $t('general.edit') }}
            </BaseButton>
          </router-link>
        </div>
      </template>
    </BasePageHeader>

    <div class="flex flex-wrap items-center gap-3 mt-2">
      <EngagementTypeBadge :type="engagement.type" />
      <EngagementStatusBadge :status="engagement.status" />
      <router-link
        :to="`/admin/customers/${engagement.customer.id}/view`"
        class="text-sm font-medium text-primary-600 hover:text-primary-700"
      >
        {{ engagement.customer.name }}
      </router-link>
      <span v-if="engagement.auto_invoice" class="text-xs text-gray-500">
        · {{ $t('engagements.view.auto_on') }}
      </span>
    </div>
    <p v-if="engagement.description" class="max-w-3xl mt-3 text-sm text-gray-600 whitespace-pre-line">
      {{ engagement.description }}
    </p>

    <!-- Summary -->
    <div class="grid gap-4 mt-6 sm:grid-cols-2 xl:grid-cols-4">
      <div
        v-for="card in summaryCards"
        :key="card.key"
        class="p-5 bg-white border border-gray-100 rounded-2xl shadow-sm"
      >
        <p class="text-sm text-gray-500">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-semibold" :class="card.emphasis ? 'text-primary-700' : 'text-gray-900'">
          <BaseFormatMoney :amount="card.amount" :currency="currency" />
        </p>
        <p v-if="card.percent !== null && card.percent !== undefined" class="mt-1 text-xs text-gray-400">
          {{ card.percent }}% {{ $t('engagements.view.of_contract') }}
        </p>
      </div>
    </div>

    <div
      v-if="isProject"
      class="p-5 mt-4 bg-white border border-gray-100 rounded-2xl shadow-sm"
    >
      <EngagementProgress :summary="engagement.summary" show-legend />
    </div>

    <!-- Milestones -->
    <section v-if="isProject" class="mt-8">
      <h3 class="text-lg font-semibold text-gray-900">{{ $t('engagements.view.milestones') }}</h3>
      <ol class="mt-4 space-y-3">
        <li
          v-for="milestone in engagement.milestones"
          :key="milestone.id"
          class="flex flex-wrap items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm"
        >
          <span
            class="flex items-center justify-center w-9 h-9 rounded-full shrink-0"
            :class="milestoneStyle(milestone).dot"
          >
            <BaseIcon :name="milestoneStyle(milestone).icon" class="w-4 h-4" />
          </span>

          <div class="flex-1 min-w-[12rem]">
            <p class="font-medium text-gray-900">{{ milestone.name }}</p>
            <p class="text-sm text-gray-500">
              <span v-if="milestone.percent !== null">{{ milestone.percent }}% · </span>
              <span v-if="milestone.due_date">{{ $t('engagements.view.due') }} {{ milestone.due_date }}</span>
              <span v-else>{{ $t('engagements.view.no_due_date') }}</span>
              <span v-if="milestone.settled_at">
                · {{ $t('engagements.view.settled_on', { date: milestone.settled_at }) }}
                <template v-if="milestone.settled_reference">({{ milestone.settled_reference }})</template>
              </span>
            </p>
          </div>

          <div class="text-right">
            <p class="font-semibold text-gray-900 tabular-nums">
              <BaseFormatMoney :amount="milestone.amount" :currency="currency" />
            </p>
            <p class="text-xs" :class="milestoneStyle(milestone).text">
              {{ $t(`engagements.milestone_statuses.${milestone.status}`) }}
            </p>
          </div>

          <div class="w-full sm:w-auto">
            <router-link
              v-if="milestone.invoice"
              :to="`/admin/invoices/${milestone.invoice.id}/view`"
            >
              <BaseButton variant="primary-outline" size="sm">
                {{ milestone.invoice.invoice_number }}
              </BaseButton>
            </router-link>
            <BaseButton
              v-else-if="milestone.status === 'PLANNED' && userStore.hasAbilities(abilities.CREATE_INVOICE)"
              variant="primary"
              size="sm"
              :loading="invoicingId === milestone.id"
              :disabled="invoicingId !== null"
              @click="createInvoice(milestone)"
            >
              <template #left="slotProps">
                <BaseIcon name="DocumentTextIcon" :class="slotProps.class" />
              </template>
              {{ $t('engagements.view.create_invoice') }}
            </BaseButton>
          </div>
        </li>
      </ol>
    </section>

    <!-- Recurring invoices of a retainer or service -->
    <section v-else class="mt-8">
      <h3 class="text-lg font-semibold text-gray-900">{{ $t('engagements.view.recurring') }}</h3>
      <p v-if="!engagement.recurring_invoices.length" class="mt-3 text-sm text-gray-500">
        {{ $t('engagements.view.no_recurring') }}
      </p>
      <div v-else class="mt-4 space-y-2">
        <router-link
          v-for="recurring in engagement.recurring_invoices"
          :key="recurring.id"
          :to="`/admin/recurring-invoices/${recurring.id}/view`"
          class="flex items-center justify-between p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md"
        >
          <span class="text-sm text-gray-900">
            {{ recurring.frequency }} · {{ $t('engagements.form.next_invoice') }} {{ recurring.next_invoice_at || '—' }}
          </span>
          <BaseFormatMoney :amount="recurring.total" :currency="currency" />
        </router-link>
      </div>
    </section>

    <!-- Invoices -->
    <section class="mt-8 mb-10">
      <h3 class="text-lg font-semibold text-gray-900">{{ $t('engagements.view.invoices') }}</h3>
      <p v-if="!engagement.invoices.length" class="mt-3 text-sm text-gray-500">
        {{ $t('engagements.view.no_invoices') }}
      </p>
      <div v-else class="mt-4 overflow-hidden bg-white border border-gray-100 rounded-2xl shadow-sm">
        <table class="w-full text-sm">
          <thead class="text-xs text-left text-gray-500 uppercase bg-gray-50">
            <tr>
              <th class="px-5 py-3">{{ $t('engagements.view.number') }}</th>
              <th class="px-5 py-3">{{ $t('engagements.view.date') }}</th>
              <th class="px-5 py-3">{{ $t('engagements.view.status') }}</th>
              <th class="px-5 py-3 text-right">{{ $t('engagements.view.total') }}</th>
              <th class="px-5 py-3 text-right">{{ $t('engagements.view.due_amount') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="invoice in engagement.invoices" :key="invoice.id">
              <td class="px-5 py-3">
                <router-link :to="`/admin/invoices/${invoice.id}/view`" class="font-medium text-primary-600">
                  {{ invoice.invoice_number }}
                </router-link>
              </td>
              <td class="px-5 py-3 text-gray-600">{{ invoice.invoice_date }}</td>
              <td class="px-5 py-3">
                <BasePaidStatusBadge :status="invoice.paid_status">
                  <BaseInvoiceStatusLabel :status="invoice.paid_status" />
                </BasePaidStatusBadge>
              </td>
              <td class="px-5 py-3 text-right tabular-nums">
                <BaseFormatMoney :amount="invoice.total" :currency="currency" />
              </td>
              <td class="px-5 py-3 text-right tabular-nums">
                <BaseFormatMoney :amount="invoice.due_amount" :currency="currency" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </BasePage>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useEngagementStore } from '@/scripts/admin/stores/engagement'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import abilities from '@/scripts/admin/stub/abilities'
import EngagementTypeBadge from './partials/EngagementTypeBadge.vue'
import EngagementStatusBadge from './partials/EngagementStatusBadge.vue'
import EngagementProgress from './partials/EngagementProgress.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const engagementStore = useEngagementStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()

const invoicingId = ref(null)

const engagement = computed(() => engagementStore.current)
const isProject = computed(() => engagement.value?.type === 'PROJECT')
const currency = computed(
  () => engagement.value?.customer.currency || companyStore.selectedCompanyCurrency
)
const canDelete = computed(
  () => engagement.value && !engagement.value.invoices.length && userStore.hasAbilities(abilities.DELETE_INVOICE)
)

const summaryCards = computed(() => {
  const s = engagement.value.summary
  return isProject.value
    ? [
        { key: 'contract', label: t('engagements.view.contract'), amount: s.contract, percent: null },
        { key: 'invoiced', label: t('engagements.invoiced'), amount: s.invoiced, percent: s.invoiced_percent },
        { key: 'paid', label: t('engagements.paid'), amount: s.paid, percent: s.paid_percent },
        { key: 'pending', label: t('engagements.pending'), amount: s.pending, percent: 100 - s.paid_percent, emphasis: true },
      ]
    : [
        { key: 'invoiced', label: t('engagements.invoiced'), amount: s.invoiced, percent: null },
        { key: 'paid', label: t('engagements.paid'), amount: s.paid, percent: null },
        { key: 'pending', label: t('engagements.outstanding'), amount: s.pending, percent: null, emphasis: true },
      ]
})

function milestoneStyle(milestone) {
  return {
    PLANNED: { icon: 'CalendarIcon', dot: 'bg-gray-100 text-gray-500', text: 'text-gray-500' },
    INVOICED: { icon: 'PaperAirplaneIcon', dot: 'bg-primary-100 text-primary-700', text: 'text-primary-700' },
    PAID: { icon: 'CheckIcon', dot: 'bg-green-100 text-green-700', text: 'text-green-700' },
    SETTLED: { icon: 'CheckIcon', dot: 'bg-green-100 text-green-700', text: 'text-green-700' },
  }[milestone.status]
}

async function createInvoice(milestone) {
  invoicingId.value = milestone.id
  try {
    const response = await engagementStore.invoiceMilestone(engagement.value.id, milestone.id)
    notificationStore.showNotification({
      type: 'success',
      message: t('engagements.view.invoice_created', { number: response.data.data.invoice_number }),
    })
  } finally {
    invoicingId.value = null
  }
}

function remove() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('engagements.view.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed) => {
      if (!confirmed) return
      await engagementStore.deleteEngagement(engagement.value.id)
      router.push('/admin/engagements')
    })
}

engagementStore.current = null
engagementStore.fetchEngagement(route.params.id)
</script>
