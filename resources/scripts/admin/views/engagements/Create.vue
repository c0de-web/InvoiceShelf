<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('engagements.title')" to="/admin/engagements" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form class="max-w-5xl mt-6 space-y-6" @submit.prevent="save">
      <!-- Type -->
      <section class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
        <h3 class="font-semibold text-gray-900">{{ $t('engagements.form.kind') }}</h3>
        <div class="grid gap-3 mt-4 sm:grid-cols-3">
          <button
            v-for="option in typeOptions"
            :key="option.value"
            type="button"
            :disabled="isEdit"
            class="flex flex-col items-start p-4 text-left border rounded-xl transition-colors disabled:cursor-not-allowed"
            :class="
              form.type === option.value
                ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500'
                : 'border-gray-200 hover:border-primary-300 disabled:opacity-50'
            "
            @click="form.type = option.value"
          >
            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-white text-primary-600 shadow-sm">
              <BaseIcon :name="option.icon" class="w-5 h-5" />
            </span>
            <span class="mt-3 font-medium text-gray-900">{{ $t(`engagements.types.${option.value}`, 1) }}</span>
            <span class="mt-1 text-sm text-gray-500">{{ $t(`engagements.type_help.${option.value}`) }}</span>
          </button>
        </div>
      </section>

      <!-- Details -->
      <section class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
        <h3 class="font-semibold text-gray-900">{{ $t('engagements.form.details') }}</h3>
        <BaseInputGrid class="mt-4">
          <BaseInputGroup :label="$t('engagements.form.customer')" required>
            <BaseCustomerSelectInput v-model="form.customer_id" show-action />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('engagements.form.name')" required>
            <BaseInput v-model="form.name" :placeholder="$t('engagements.form.name_placeholder')" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('engagements.form.start_date')">
            <BaseDatePicker v-model="form.start_date" :calendar-button="true" calendar-button-icon="calendar" />
          </BaseInputGroup>

          <BaseInputGroup v-if="isEdit" :label="$t('engagements.form.status')">
            <BaseMultiselect
              v-model="form.status"
              :options="statusOptions"
              label="label"
              value-prop="value"
              :can-deselect="false"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="needsExchangeRate"
            :label="$t('engagements.form.exchange_rate')"
            :help-text="$t('engagements.form.exchange_rate_help', { currency: customerCurrency?.code })"
            required
          >
            <BaseInput v-model="form.exchange_rate" type="number" step="0.000001" min="0" />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseInputGroup class="mt-4" :label="$t('engagements.form.description')">
          <BaseTextarea v-model="form.description" rows="3" />
        </BaseInputGroup>
      </section>

      <!-- Project: contract and payment schedule -->
      <section v-if="form.type === 'PROJECT'" class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h3 class="font-semibold text-gray-900">{{ $t('engagements.form.schedule') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ $t('engagements.form.schedule_help') }}</p>
          </div>
          <div class="w-full sm:w-64">
            <BaseInputGroup :label="$t('engagements.form.contract_value')" required>
              <BaseMoney v-model="contractMajor" :currency="currency" />
            </BaseInputGroup>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 mt-5">
          <button
            v-for="template in templates"
            :key="template.key"
            type="button"
            class="px-3 py-1.5 text-sm border rounded-full transition-colors"
            :class="
              activeTemplate === template.key
                ? 'border-primary-500 bg-primary-50 text-primary-700'
                : 'border-gray-200 text-gray-600 hover:border-primary-300'
            "
            :disabled="hasLockedMilestones"
            @click="applyTemplate(template)"
          >
            {{ template.label }}
          </button>
        </div>

        <div class="mt-5 space-y-3">
          <div
            v-for="(milestone, index) in milestones"
            :key="milestone.key"
            class="p-4 border rounded-xl"
            :class="milestone.is_locked ? 'border-gray-100 bg-gray-50' : 'border-gray-200'"
          >
            <div class="flex flex-wrap items-start gap-3">
              <span class="flex items-center justify-center w-7 h-7 mt-1 text-sm font-medium rounded-full bg-primary-50 text-primary-700 shrink-0">
                {{ index + 1 }}
              </span>

              <div class="flex-1 min-w-[12rem]">
                <BaseInput
                  v-model="milestone.name"
                  :disabled="milestone.is_locked"
                  :placeholder="$t('engagements.form.milestone_name')"
                />
              </div>

              <div class="flex items-center gap-2">
                <div class="inline-flex p-0.5 bg-gray-100 rounded-lg">
                  <button
                    v-for="mode in ['percent', 'amount']"
                    :key="mode"
                    type="button"
                    :disabled="milestone.is_locked"
                    class="px-2.5 py-1 text-xs rounded-md"
                    :class="milestone.mode === mode ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                    @click="milestone.mode = mode"
                  >
                    {{ mode === 'percent' ? '%' : currency?.symbol || '$' }}
                  </button>
                </div>
                <BaseInput
                  v-if="milestone.mode === 'percent'"
                  v-model="milestone.percent"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="w-24"
                  :disabled="milestone.is_locked"
                />
                <BaseMoney
                  v-else
                  v-model="milestone.amountMajor"
                  :currency="currency"
                  class="w-36"
                  :disabled="milestone.is_locked"
                />
              </div>

              <div class="w-32 pt-2 text-sm text-right text-gray-700 tabular-nums">
                <BaseFormatMoney :amount="amounts[index]" :currency="currency" />
              </div>

              <button
                v-if="!milestone.is_locked && milestones.length > 1"
                type="button"
                class="p-2 text-gray-400 rounded-lg hover:text-red-500 hover:bg-red-50"
                :title="$t('general.delete')"
                @click="milestones.splice(index, 1)"
              >
                <BaseIcon name="TrashIcon" class="w-4 h-4" />
              </button>
              <span
                v-else-if="milestone.is_locked"
                class="inline-flex items-center gap-1 pt-2 text-xs text-gray-500"
              >
                <BaseIcon name="LockClosedIcon" class="w-3.5 h-3.5" />
                {{ $t(`engagements.milestone_statuses.${milestone.status}`) }}
              </span>
            </div>

            <div class="flex flex-wrap items-center gap-4 mt-3 pl-10">
              <div class="w-48">
                <BaseDatePicker
                  v-model="milestone.due_date"
                  :placeholder="$t('engagements.form.due_date')"
                  :calendar-button="true"
                  calendar-button-icon="calendar"
                />
              </div>

              <label
                v-if="!milestone.invoice_id"
                class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer"
              >
                <input v-model="milestone.settled" type="checkbox" class="rounded text-primary-600" />
                {{ $t('engagements.form.settled_before') }}
              </label>

              <template v-if="milestone.settled">
                <div class="w-44">
                  <BaseDatePicker v-model="milestone.settled_at" :placeholder="$t('engagements.form.settled_at')" />
                </div>
                <BaseInput
                  v-model="milestone.settled_reference"
                  class="w-56"
                  :placeholder="$t('engagements.form.settled_reference')"
                />
              </template>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700"
            @click="addMilestone()"
          >
            <BaseIcon name="PlusCircleIcon" class="w-4 h-4" />
            {{ $t('engagements.form.add_milestone') }}
          </button>

          <div
            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg"
            :class="scheduleBalanced ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
          >
            <BaseIcon :name="scheduleBalanced ? 'CheckCircleIcon' : 'ExclamationCircleIcon'" class="w-4 h-4" />
            <span v-if="scheduleBalanced">{{ $t('engagements.form.balanced') }}</span>
            <span v-else-if="!contractMinor">{{ $t('engagements.form.enter_contract') }}</span>
            <span v-else>
              {{ $t('engagements.form.unbalanced') }}
              <BaseFormatMoney :amount="contractMinor - totalMinor" :currency="currency" />
            </span>
          </div>
        </div>

        <div class="flex items-start justify-between gap-6 pt-5 mt-6 border-t border-gray-100">
          <div>
            <p class="font-medium text-gray-900">{{ $t('engagements.form.auto_invoice') }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ $t('engagements.form.auto_invoice_help') }}</p>
          </div>
          <BaseSwitch v-model="form.auto_invoice" class="shrink-0" />
        </div>
      </section>

      <!-- Retainer / service: linked recurring invoices -->
      <section v-else class="p-6 bg-white border border-gray-100 rounded-2xl shadow-sm">
        <h3 class="font-semibold text-gray-900">{{ $t('engagements.form.recurring') }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $t('engagements.form.recurring_help') }}</p>

        <p v-if="!form.customer_id" class="mt-4 text-sm text-gray-400">
          {{ $t('engagements.form.pick_customer_first') }}
        </p>
        <p v-else-if="!recurringOptions.length" class="mt-4 text-sm text-gray-500">
          {{ $t('engagements.form.no_recurring') }}
          <router-link to="/admin/recurring-invoices/create" class="font-medium text-primary-600">
            {{ $t('engagements.form.create_recurring') }}
          </router-link>
        </p>
        <div v-else class="mt-4 space-y-2">
          <label
            v-for="recurring in recurringOptions"
            :key="recurring.id"
            class="flex items-center justify-between gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:border-primary-300"
          >
            <span class="inline-flex items-center gap-3">
              <input v-model="form.recurring_invoice_ids" type="checkbox" :value="recurring.id" class="rounded text-primary-600" />
              <span>
                <span class="block text-sm font-medium text-gray-900">
                  {{ recurring.formatted_frequency || recurring.frequency }}
                </span>
                <span class="block text-xs text-gray-500">
                  {{ $t('engagements.form.next_invoice') }} {{ recurring.formatted_next_invoice_at || '—' }}
                </span>
              </span>
            </span>
            <BaseFormatMoney :amount="recurring.total" :currency="currency" />
          </label>
        </div>
      </section>

      <div class="flex justify-end gap-3">
        <router-link :to="isEdit ? `/admin/engagements/${route.params.id}/view` : '/admin/engagements'">
          <BaseButton type="button" variant="primary-outline">{{ $t('general.cancel') }}</BaseButton>
        </router-link>
        <BaseButton type="submit" variant="primary" :loading="isSaving" :disabled="isSaving || !canSave">
          <template #left="slotProps">
            <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('engagements.form.save') }}
        </BaseButton>
      </div>
    </form>
  </BasePage>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import http from '@/scripts/http'
import { useEngagementStore, SCHEDULE_TEMPLATES } from '@/scripts/admin/stores/engagement'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotificationStore } from '@/scripts/stores/notification'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const engagementStore = useEngagementStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()

const isEdit = computed(() => route.name === 'engagements.edit')
const pageTitle = computed(() => (isEdit.value ? t('engagements.edit') : t('engagements.new')))

const typeOptions = [
  { value: 'PROJECT', icon: 'BriefcaseIcon' },
  { value: 'RETAINER', icon: 'ArrowPathIcon' },
  { value: 'SERVICE', icon: 'CircleStackIcon' },
]

const statusOptions = computed(() =>
  ['ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'].map((value) => ({
    value,
    label: t(`engagements.statuses.${value}`),
  }))
)

const form = reactive({
  id: null,
  type: 'PROJECT',
  customer_id: null,
  name: '',
  description: '',
  start_date: null,
  status: 'ACTIVE',
  exchange_rate: null,
  auto_invoice: false,
  estimate_id: null,
  recurring_invoice_ids: [],
})

const contractMajor = ref(0)
const contractMinor = computed(() => Math.round((Number(contractMajor.value) || 0) * 100))

let nextKey = 1
const milestones = ref([])
const activeTemplate = ref('25-25-50')
const isSaving = ref(false)
const customerCurrency = ref(null)
const recurringOptions = ref([])

const currency = computed(() => customerCurrency.value || companyStore.selectedCompanyCurrency)
const needsExchangeRate = computed(
  () => customerCurrency.value && customerCurrency.value.id !== companyStore.selectedCompanyCurrency?.id
)

const templates = computed(() => [
  ...SCHEDULE_TEMPLATES.map((template) => ({ ...template, label: template.percents.join(' / ') })),
  { key: 'custom', label: t('engagements.form.custom'), percents: [], names: [] },
])

const hasLockedMilestones = computed(() => milestones.value.some((m) => m.is_locked))

function makeMilestone(values = {}) {
  return {
    key: nextKey++,
    id: null,
    name: '',
    mode: 'percent',
    percent: null,
    amountMajor: 0,
    due_date: null,
    settled: false,
    settled_at: null,
    settled_reference: '',
    is_locked: false,
    invoice_id: null,
    status: 'PLANNED',
    ...values,
  }
}

function addMilestone(values = {}) {
  milestones.value.push(makeMilestone(values))
  activeTemplate.value = 'custom'
}

function applyTemplate(template) {
  activeTemplate.value = template.key
  milestones.value = template.percents.length
    ? template.percents.map((percent, i) =>
        makeMilestone({ name: t(`engagements.milestone_names.${template.names[i]}`), percent })
      )
    : [makeMilestone()]
}

// Mirrors the server: a percentage is its floored share, and a last percentage takes the rest.
const amounts = computed(() => {
  const list = milestones.value.map((m) =>
    m.mode === 'percent'
      ? Math.floor((contractMinor.value * (Number(m.percent) || 0)) / 100)
      : Math.round((Number(m.amountMajor) || 0) * 100)
  )
  const last = milestones.value.length - 1
  if (last >= 0 && milestones.value[last].mode === 'percent' && Number(milestones.value[last].percent)) {
    list[last] = contractMinor.value - list.slice(0, last).reduce((sum, a) => sum + a, 0)
  }
  return list
})

const totalMinor = computed(() => amounts.value.reduce((sum, a) => sum + a, 0))
const scheduleBalanced = computed(() => contractMinor.value > 0 && totalMinor.value === contractMinor.value)

const canSave = computed(() => {
  if (!form.customer_id || !form.name) return false
  if (form.type === 'PROJECT') return scheduleBalanced.value && milestones.value.every((m) => m.name)
  return true
})

watch(
  () => form.customer_id,
  async (id) => {
    customerCurrency.value = null
    recurringOptions.value = []
    if (!id) return

    const [customer, recurring] = await Promise.all([
      http.get(`/api/v1/customers/${id}`),
      engagementStore.fetchCustomerRecurringInvoices(id),
    ])
    customerCurrency.value = customer.data.data.currency || null
    recurringOptions.value = recurring.data.data
  }
)

function payload() {
  const data = { ...form }

  if (form.type === 'PROJECT') {
    data.contract_value = contractMinor.value
    data.milestones = milestones.value.map((m, index) => ({
      id: m.id,
      name: m.name,
      // Locked milestones are sent as their fixed amount, so they stay exactly as invoiced.
      ...(m.mode === 'percent' && !m.is_locked
        ? { percent: Number(m.percent) }
        : { amount: m.is_locked ? m.lockedAmount : amounts.value[index] }),
      due_date: m.due_date || null,
      settled_at: m.settled ? m.settled_at : null,
      settled_reference: m.settled ? m.settled_reference : null,
    }))
    data.recurring_invoice_ids = []
  } else {
    data.milestones = []
    data.contract_value = null
  }

  return data
}

async function save() {
  isSaving.value = true
  try {
    const response = await engagementStore.saveEngagement(payload())
    notificationStore.showNotification({ type: 'success', message: t('engagements.saved') })
    router.push(`/admin/engagements/${response.data.data.id}/view`)
  } finally {
    isSaving.value = false
  }
}

async function load() {
  if (!isEdit.value) {
    applyTemplate(templates.value[0])
    return
  }

  const { data } = await engagementStore.fetchEngagement(route.params.id)
  const engagement = data.data

  Object.assign(form, {
    id: engagement.id,
    type: engagement.type,
    customer_id: engagement.customer_id,
    name: engagement.name,
    description: engagement.description || '',
    start_date: engagement.start_date,
    status: engagement.status,
    exchange_rate: engagement.exchange_rate,
    auto_invoice: engagement.auto_invoice,
    estimate_id: engagement.estimate_id,
    recurring_invoice_ids: engagement.recurring_invoices.map((r) => r.id),
  })
  contractMajor.value = (engagement.contract_value || 0) / 100
  activeTemplate.value = 'custom'
  milestones.value = engagement.milestones.map((m) =>
    makeMilestone({
      id: m.id,
      name: m.name,
      mode: m.percent !== null && !m.is_locked ? 'percent' : 'amount',
      percent: m.percent,
      amountMajor: m.amount / 100,
      lockedAmount: m.amount,
      due_date: m.due_date,
      settled: !!m.settled_at,
      settled_at: m.settled_at,
      settled_reference: m.settled_reference || '',
      is_locked: m.is_locked,
      invoice_id: m.invoice_id,
      status: m.status,
    })
  )
}

load()
</script>
