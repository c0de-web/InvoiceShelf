<template>
  <BasePage>
    <BasePageHeader :title="$t('navigation.item_categories')" class="mb-6">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('navigation.items')" to="/admin/items" />
        <BaseBreadcrumbItem
          :title="$t('navigation.item_categories')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <ItemCategorySetting />
  </BasePage>
</template>

<script setup>
import ItemCategorySetting from '@/scripts/admin/views/settings/ItemCategorySetting.vue'
</script>
