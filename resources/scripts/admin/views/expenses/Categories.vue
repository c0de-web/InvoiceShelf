<template>
  <BasePage>
    <BasePageHeader :title="$t('navigation.expense_categories')" class="mb-6">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('navigation.expenses')"
          to="/admin/expenses"
        />
        <BaseBreadcrumbItem
          :title="$t('navigation.expense_categories')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <ExpenseCategorySetting />
  </BasePage>
</template>

<script setup>
import ExpenseCategorySetting from '@/scripts/admin/views/settings/ExpenseCategorySetting.vue'
</script>
