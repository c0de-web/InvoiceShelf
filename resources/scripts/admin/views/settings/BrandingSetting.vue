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
  </BaseSettingCard>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { brandPalette, isBrandColor } from '@/scripts/helpers/brand-color'

const DEFAULT_COLOR = '#5851DB'

const companyStore = useCompanyStore()
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
</script>
