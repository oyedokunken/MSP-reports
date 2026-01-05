import { api } from '@/lib/api'
import type {
  ReportTemplate,
  CreateTemplateRequest,
  UpdateTemplateRequest,
  ApplyTemplateRequest,
  ApplyTemplateBulkRequest,
  AvailableReportTypesResponse,
} from '@/pages/templates/types/template'

export const templateApi = {
  list: async (params?: { report_type?: string; active?: boolean }) => {
    const response = await api.get<ReportTemplate[]>('/report-templates', { params })
    return response.data
  },

  get: async (id: number) => {
    const response = await api.get<ReportTemplate>(`/report-templates/${id}`)
    return response.data
  },

  create: async (data: CreateTemplateRequest) => {
    const response = await api.post<ReportTemplate>('/report-templates', data)
    return response.data
  },

  update: async (id: number, data: UpdateTemplateRequest) => {
    const response = await api.put<ReportTemplate>(`/report-templates/${id}`, data)
    return response.data
  },

  delete: async (id: number) => {
    const response = await api.delete<{ message: string }>(`/report-templates/${id}`)
    return response.data
  },

  applyToCustomer: async (id: number, data: ApplyTemplateRequest) => {
    const response = await api.post(`/report-templates/${id}/apply-to-customer`, data)
    return response.data
  },

  applyToCustomers: async (id: number, data: ApplyTemplateBulkRequest) => {
    const response = await api.post(`/report-templates/${id}/apply-to-customers`, data)
    return response.data
  },

  getAvailableReportTypes: async (customerId: number) => {
    const response = await api.get<AvailableReportTypesResponse>(
      '/report-templates/available-for-customer',
      { customer_id: customerId }
    )
    return response.data
  },

  preview: async (id: number) => {
    const response = await api.post<{ generated_report_id: number; status: string }>(
      `/report-templates/${id}/preview`
    )
    return response.data
  },
}
