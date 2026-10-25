import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'

// Schedules offered when setting up a project; "custom" starts from one empty row.
export const SCHEDULE_TEMPLATES = [
  { key: '25-25-50', percents: [25, 25, 50], names: ['deposit', 'progress', 'completion'] },
  { key: '50-50', percents: [50, 50], names: ['deposit', 'completion'] },
]

const request = async (call) => {
  try {
    return await call()
  } catch (err) {
    handleError(err)
    throw err
  }
}

export const useEngagementStore = defineStore('engagement', {
  state: () => ({
    engagements: [],
    current: null,
  }),

  actions: {
    async fetchEngagements(params = {}) {
      const response = await request(() =>
        http.get('/api/v1/engagements', { params: { limit: 100, ...params } })
      )
      this.engagements = response.data.data
      return response
    },

    async fetchEngagement(id) {
      const response = await request(() => http.get(`/api/v1/engagements/${id}`))
      this.current = response.data.data
      return response
    },

    saveEngagement(data) {
      return request(() =>
        data.id
          ? http.put(`/api/v1/engagements/${data.id}`, data)
          : http.post('/api/v1/engagements', data)
      )
    },

    deleteEngagement(id) {
      return request(() => http.delete(`/api/v1/engagements/${id}`))
    },

    async invoiceMilestone(engagementId, milestoneId) {
      const response = await request(() =>
        http.post(`/api/v1/engagements/${engagementId}/milestones/${milestoneId}/invoice`)
      )
      await this.fetchEngagement(engagementId)
      return response
    },

    fetchCustomerRecurringInvoices(customerId) {
      return request(() =>
        http.get('/api/v1/recurring-invoices', {
          params: { customer_id: customerId, limit: 'all' },
        })
      )
    },
  },
})
