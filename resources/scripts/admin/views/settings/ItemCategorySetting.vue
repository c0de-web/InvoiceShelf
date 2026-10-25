<template>
  <BaseModal :show="isModalOpen" @close="closeModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{
          form.id
            ? $t('settings.item_categories.edit_category')
            : $t('settings.item_categories.add_category')
        }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="saveCategory">
      <div class="p-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup :label="$t('settings.item_categories.name')" required>
            <BaseInput v-model.trim="form.name" type="text" />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.item_categories.parent')"
            :help-text="$t('settings.item_categories.parent_help')"
          >
            <BaseMultiselect
              v-model="form.parent_id"
              :options="parentOptions"
              label="name"
              value-prop="id"
              track-by="name"
              searchable
              :placeholder="$t('settings.item_categories.no_parent')"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.item_categories.description')">
            <BaseTextarea v-model="form.description" rows="3" />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="flex justify-end p-4 border-t border-gray-200 border-solid">
        <BaseButton
          type="button"
          variant="primary-outline"
          class="mr-3 text-sm"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving || !form.name"
          variant="primary"
          type="submit"
        >
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>

  <BaseSettingCard
    :title="$t('settings.item_categories.title')"
    :description="$t('settings.item_categories.description_text')"
  >
    <template #action>
      <BaseButton variant="primary-outline" type="button" @click="openModal()">
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.item_categories.add_category') }}
      </BaseButton>
    </template>

    <p
      v-if="!categoryStore.categories.length"
      class="mt-16 text-sm text-gray-500"
    >
      {{ $t('settings.item_categories.empty') }}
    </p>

    <ul v-else class="mt-16 divide-y divide-gray-100">
      <template v-for="top in categoryStore.topLevel" :key="top.id">
        <li class="flex items-center justify-between py-3">
          <div>
            <p class="font-medium text-gray-900">{{ top.name }}</p>
            <p v-if="top.description" class="text-sm text-gray-500">
              {{ top.description }}
            </p>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500">
              {{ $t('settings.item_categories.items_count', top.items_count) }}
            </span>
            <BaseIcon
              name="PencilIcon"
              class="w-4 h-4 text-gray-500 cursor-pointer"
              @click="openModal(top)"
            />
            <BaseIcon
              name="TrashIcon"
              class="w-4 h-4 text-gray-500 cursor-pointer"
              @click="confirmDelete(top)"
            />
          </div>
        </li>
        <li
          v-for="child in categoryStore.childrenOf(top.id)"
          :key="child.id"
          class="flex items-center justify-between py-2 pl-8"
        >
          <div>
            <p class="text-gray-800">{{ child.name }}</p>
            <p v-if="child.description" class="text-sm text-gray-500">
              {{ child.description }}
            </p>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-500">
              {{ $t('settings.item_categories.items_count', child.items_count) }}
            </span>
            <BaseIcon
              name="PencilIcon"
              class="w-4 h-4 text-gray-500 cursor-pointer"
              @click="openModal(child)"
            />
            <BaseIcon
              name="TrashIcon"
              class="w-4 h-4 text-gray-500 cursor-pointer"
              @click="confirmDelete(child)"
            />
          </div>
        </li>
      </template>
    </ul>
  </BaseSettingCard>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useItemCategoryStore } from '@/scripts/admin/stores/item-category'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useNotificationStore } from '@/scripts/stores/notification'

const categoryStore = useItemCategoryStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isModalOpen = ref(false)
const isSaving = ref(false)
const form = reactive({ id: null, name: '', parent_id: null, description: '' })

// Only top-level categories can hold subcategories, and never the one being edited.
const parentOptions = computed(() =>
  categoryStore.topLevel.filter((c) => c.id !== form.id)
)

categoryStore.fetchCategories()

function openModal(category = null) {
  Object.assign(form, {
    id: category?.id ?? null,
    name: category?.name ?? '',
    parent_id: category?.parent_id ?? null,
    description: category?.description ?? '',
  })
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
}

async function saveCategory() {
  isSaving.value = true

  try {
    await categoryStore.saveCategory({ ...form })
    notificationStore.showNotification({
      type: 'success',
      message: t('settings.item_categories.saved'),
    })
    closeModal()
  } finally {
    isSaving.value = false
  }
}

function confirmDelete(category) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.item_categories.confirm_delete', { name: category.name }),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed) => {
      if (!confirmed) {
        return
      }

      await categoryStore.deleteCategory(category.id)
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.item_categories.deleted'),
      })
    })
}
</script>
