<template>
  <BaseSettingCard
    :title="$t('settings.branding.title')"
    :description="$t('settings.branding.description')"
  >
    <form class="mt-14" @submit.prevent="save(form.brand_color)">
      <BaseInputGroup
        :label="$t('settings.branding.brand_color')"
        :help-text="$t('settings.branding.brand_color_help')"
        :error="hexError"
      >
        <div class="flex items-center gap-3">
          <input
            v-model="pickerValue"
            type="color"
            class="h-10 w-14 cursor-pointer rounded border border-gray-200 p-1"
            :aria-label="$t('settings.branding.brand_color')"
          />
          <BaseInput
            v-model.trim="form.brand_color"
            class="max-w-[10rem]"
            placeholder="#5851DB"
            :invalid="!!hexError"
          />
        </div>
      </BaseInputGroup>

      <div v-if="palette" class="mt-6">
        <p class="text-sm font-medium text-gray-700">
          {{ $t('settings.branding.preview') }}
        </p>
        <div class="mt-2 flex flex-wrap gap-1">
          <span
            v-for="(color, shade) in palette"
            :key="shade"
            class="h-8 w-8 rounded"
            :title="shade"
            :style="{ backgroundColor: color }"
          />
        </div>
        <span
          class="mt-3 inline-block rounded px-4 py-2 text-sm text-white"
          :style="{ backgroundColor: palette[500] }"
        >
          {{ $t('settings.branding.sample_button') }}
        </span>
      </div>

      <div class="mt-8 flex gap-3">
        <BaseButton
          :disabled="isSaving || !!hexError || !form.brand_color"
          :loading="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>
          {{ $t('settings.branding.save') }}
        </BaseButton>

        <BaseButton
          v-if="savedColor"
          :disabled="isSaving"
          variant="primary-outline"
          type="button"
          @click="save(null)"
        >
          {{ $t('settings.branding.reset') }}
        </BaseButton>
      </div>
    </form>

    <BaseDivider class="my-8" />

    <BaseInputGroup
      :label="$t('settings.branding.header_logo')"
      :help-text="$t('settings.branding.header_logo_help')"
    >
      <BaseFileUploader
        v-model="headerLogoPreview"
        base64
        @change="(name, data, count, file) => uploadImage('header_logo', file.name, data)"
        @remove="removeImage('header_logo')"
      />
    </BaseInputGroup>

    <BaseInputGroup
      class="mt-8"
      :label="$t('settings.branding.app_icon')"
      :help-text="$t('settings.branding.app_icon_help')"
    >
      <BaseFileUploader
        v-model="appIconPreview"
        base64
        @change="(name, data, count, file) => uploadImage('app_icon', file.name, data)"
        @remove="removeImage('app_icon')"
      />
    </BaseInputGroup>

    <BaseDivider class="my-8" />

    <h3 class="text-lg font-medium text-gray-900">
      {{ $t('settings.branding.login_page') }}
    </h3>
    <p class="mt-1 text-sm text-gray-500">
      {{ $t('settings.branding.login_page_help') }}
    </p>

    <BaseInputGroup class="mt-6" :label="$t('settings.branding.login_logo')">
      <BaseFileUploader
        v-model="loginLogoPreview"
        base64
        @change="(name, data, count, file) => uploadImage('login_logo', file.name, data)"
        @remove="removeImage('login_logo')"
      />
    </BaseInputGroup>

    <form class="mt-6 space-y-6" @submit.prevent="saveLoginPage">
      <BaseInputGroup :label="$t('settings.branding.login_heading')">
        <BaseInput
          v-model="loginForm.login_page_heading"
          :placeholder="$t('settings.branding.default_placeholder')"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('settings.branding.login_description')">
        <BaseTextarea
          v-model="loginForm.login_page_description"
          rows="3"
          :placeholder="$t('settings.branding.default_placeholder')"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.branding.copyright')"
        :help-text="$t('settings.branding.copyright_help')"
      >
        <BaseInput
          v-model="loginForm.copyright_text"
          placeholder="© C0de Web Ltd"
        />
      </BaseInputGroup>

      <BaseButton
        :disabled="isSavingLogin"
        :loading="isSavingLogin"
        variant="primary"
        type="submit"
      >
        {{ $t('settings.branding.save') }}
      </BaseButton>
    </form>
  </BaseSettingCard>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useNotificationStore } from '@/scripts/stores/notification'
import { brandPalette, isBrandColor } from '@/scripts/helpers/brand-color'

const DEFAULT_COLOR = '#5851DB'

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref(false)
const savedColor = computed(() =>
  isBrandColor(companyStore.selectedCompanySettings.brand_color)
    ? companyStore.selectedCompanySettings.brand_color
    : null
)

const form = reactive({ brand_color: savedColor.value ?? '' })

// The native picker only takes #rrggbb; it and the hex field edit the same value.
const pickerValue = computed({
  get: () => (isBrandColor(form.brand_color) ? form.brand_color : DEFAULT_COLOR),
  set: (value) => (form.brand_color = value.toUpperCase()),
})

const hexError = computed(() =>
  form.brand_color && !isBrandColor(form.brand_color)
    ? t('settings.branding.invalid_hex')
    : null
)

const palette = computed(() =>
  isBrandColor(form.brand_color) ? brandPalette(form.brand_color) : null
)

async function save(color) {
  isSaving.value = true

  try {
    await companyStore.updateCompanySettings({
      data: { settings: { brand_color: color } },
      message: 'settings.branding.saved',
    })
    form.brand_color = color ?? ''
  } finally {
    isSaving.value = false
  }
}

const headerLogoPreview = ref(
  companyStore.selectedCompany?.header_logo
    ? [{ image: companyStore.selectedCompany.header_logo }]
    : []
)

const appIconPreview = ref(
  globalStore.globalSettings.app_icon_version
    ? [{ image: `/storage/branding/app-icon-192.png?v=${globalStore.globalSettings.app_icon_version}` }]
    : []
)

const loginLogoPreview = ref(
  globalStore.globalSettings.login_page_logo
    ? [{ image: `/storage/${globalStore.globalSettings.login_page_logo}` }]
    : []
)

const loginForm = reactive({
  login_page_heading: globalStore.globalSettings.login_page_heading || '',
  login_page_description: globalStore.globalSettings.login_page_description || '',
  copyright_text: globalStore.globalSettings.copyright_text || '',
})

const isSavingLogin = ref(false)

async function saveLoginPage() {
  isSavingLogin.value = true

  try {
    await companyStore.updateLoginPage({ ...loginForm })
    Object.assign(globalStore.globalSettings, loginForm)
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.branding.login_page_saved'),
    })
  } finally {
    isSavingLogin.value = false
  }
}

const uploaders = {
  header_logo: (data) => companyStore.updateHeaderLogo(data),
  app_icon: (data) => companyStore.updateAppIcon(data),
  login_logo: (data) => companyStore.updateLoginLogo(data),
}

async function uploadImage(field, name, data) {
  const form = new FormData()
  form.append(field, JSON.stringify({ name, data }))

  await uploaders[field](form)
  notificationStore.showNotification({
    type: 'success',
    message: t(`settings.branding.${field}_saved`),
  })
}

async function removeImage(field) {
  const form = new FormData()
  form.append(`is_${field}_removed`, true)

  await uploaders[field](form)
  notificationStore.showNotification({
    type: 'success',
    message: t(`settings.branding.${field}_removed`),
  })
}
</script>
