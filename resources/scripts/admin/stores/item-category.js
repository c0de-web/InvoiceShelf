import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'

// "Hosting › Web hosting" for a subcategory, "Hosting" for a top-level category.
export function categoryLabel(category) {
  if (!category) {
    return ''
  }

  return category.parent_name
    ? `${category.parent_name} › ${category.name}`
    : category.name
}

export const useItemCategoryStore = defineStore('itemCategory', {
  state: () => ({
    categories: [],
  }),

  getters: {
    topLevel: (state) => state.categories.filter((c) => !c.parent_id),

    childrenOf: (state) => (id) =>
      state.categories.filter((c) => c.parent_id === id),

    // Top-level categories each followed by their subcategories, labelled for selects.
    options: (state) =>
      state.categories
        .filter((c) => !c.parent_id)
        .flatMap((top) => [
          top,
          ...state.categories.filter((c) => c.parent_id === top.id),
        ])
        .map((c) => ({ ...c, label: categoryLabel(c) })),
  },

  actions: {
    async fetchCategories() {
      try {
        const response = await http.get('/api/v1/item-categories')
        this.categories = response.data.data
        return response
      } catch (err) {
        handleError(err)
        throw err
      }
    },

    async saveCategory(category) {
      try {
        const response = category.id
          ? await http.put(`/api/v1/item-categories/${category.id}`, category)
          : await http.post('/api/v1/item-categories', category)
        await this.fetchCategories()
        return response
      } catch (err) {
        handleError(err)
        throw err
      }
    },

    async deleteCategory(id) {
      try {
        const response = await http.delete(`/api/v1/item-categories/${id}`)
        await this.fetchCategories()
        return response
      } catch (err) {
        handleError(err)
        throw err
      }
    },
  },
})
