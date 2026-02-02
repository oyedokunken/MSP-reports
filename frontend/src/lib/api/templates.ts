import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
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
    return api.get<ApiResponse<ReportTemplate[]>>('/report-templates', { params })
  },

  get: async (id: number) => {
    return api.get<ApiResponse<ReportTemplate>>(`/report-templates/${id}`)
  },

  create: async (data: CreateTemplateRequest) => {
    return api.post<ApiResponse<ReportTemplate>>('/report-templates', data)
  },

  update: async (id: number, data: UpdateTemplateRequest) => {
    return api.put<ApiResponse<ReportTemplate>>(`/report-templates/${id}`, data)
  },

  delete: async (id: number) => {
    return api.delete<ApiResponse<{ message: string; schedules_deleted: number }>>(`/report-templates/${id}`)
  },

  applyToCustomer: async (id: number, data: ApplyTemplateRequest) => {
    return api.post<ApiResponse<any>>(`/report-templates/${id}/apply-to-customer`, data)
  },

  applyToCustomers: async (id: number, data: ApplyTemplateBulkRequest) => {
    return api.post<ApiResponse<any[]>>(`/report-templates/${id}/apply-to-customers`, data)
  },

  getAvailableReportTypes: async (customerId: number) => {
    return api.get<ApiResponse<AvailableReportTypesResponse>>(
      '/report-templates/available-for-customer',
      { customer_id: customerId }
    )
  },

  getAllReportTypes: async () => {
    return api.get<ApiResponse<{ report_types: import('@/pages/templates/types/template').ReportTypeDefinition[] }>>(
      '/report-templates/all-report-types'
    )
  },

  preview: async (id: number) => {
    return api.post<ApiResponse<{ generated_report_id: number; status: string }>>(
      `/report-templates/${id}/preview`
    )
  },

  getAppliedCustomers: async (id: number) => {
    return api.get<ApiResponse<Array<{ customer_id: number; canonical_name: string; report_schedule_id: number }>>>(`/report-templates/${id}/applied-customers`)
  },
}
