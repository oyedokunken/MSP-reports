import { create } from 'zustand'
import type { AuditLog, AuditLogsFilters, AuditLogStats } from '@/pages/audit-logs/types'

export interface AuditLogsState {
  auditLogs: AuditLog[]
  stats: AuditLogStats | null
  isLoading: boolean
  error: string | null
  currentPage: number
  totalPages: number
  totalLogs: number
  filters: AuditLogsFilters
  
  // Actions
  fetchAuditLogs: () => Promise<void>
  setPage: (page: number) => void
  setFilters: (filters: Partial<AuditLogsFilters>) => void
  exportLogs: () => Promise<void>
}

// Mock data generator
const generateMockAuditLogs = (): AuditLog[] => {
  const eventTypes = [
    'user_login',
    'user_logout',
    'user_created',
    'user_updated',
    'user_deleted',
    'integration_created',
    'integration_updated',
    'integration_synced',
    'customer_mapped',
    'report_generated',
    'settings_updated',
  ]

  const actorTypes = ['User', 'System', 'ApiKey']
  const targetTypes = ['User', 'Integration', 'Customer', 'Report', 'Company']
  const users = ['John Doe', 'Jane Smith', 'Admin User', 'System Admin', 'Test User']
  const ipAddresses = ['192.168.1.100', '10.0.0.50', '172.16.0.25', '203.0.113.42']

  const logs: AuditLog[] = []
  const now = new Date()

  for (let i = 0; i < 50; i++) {
    const daysAgo = Math.floor(Math.random() * 30)
    const hoursAgo = Math.floor(Math.random() * 24)
    const minutesAgo = Math.floor(Math.random() * 60)
    
    const createdAt = new Date(now)
    createdAt.setDate(createdAt.getDate() - daysAgo)
    createdAt.setHours(createdAt.getHours() - hoursAgo)
    createdAt.setMinutes(createdAt.getMinutes() - minutesAgo)

    const eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)]
    const actorType = actorTypes[Math.floor(Math.random() * actorTypes.length)]
    const targetType = targetTypes[Math.floor(Math.random() * targetTypes.length)]

    logs.push({
      audit_log_id: i + 1,
      company_id: 1,
      event_type: eventType,
      actor_type: actorType,
      actor_id: actorType === 'System' ? null : Math.floor(Math.random() * 10) + 1,
      actor_name: actorType === 'System' ? 'System' : users[Math.floor(Math.random() * users.length)],
      target_type: targetType,
      target_id: Math.floor(Math.random() * 100) + 1,
      target_name: `${targetType} #${Math.floor(Math.random() * 100) + 1}`,
      event_data: {
        details: `Mock event data for ${eventType}`,
        changes: eventType.includes('updated') ? {
          before: 'old_value',
          after: 'new_value',
        } : undefined,
      },
      ip_address: ipAddresses[Math.floor(Math.random() * ipAddresses.length)],
      user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
      created_at: createdAt.toISOString(),
    })
  }

  // Sort by date descending (newest first)
  return logs.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
}

const mockStats: AuditLogStats = {
  total_logs: 1247,
  logs_today: 42,
  unique_actors: 15,
  critical_events: 3,
}

export const useAuditLogsStore = create<AuditLogsState>((set, get) => ({
  auditLogs: [],
  stats: null,
  isLoading: false,
  error: null,
  currentPage: 1,
  totalPages: 5,
  totalLogs: 50,
  filters: {
    search: '',
    event_type: '',
    actor_type: '',
    target_type: '',
    date_from: '',
    date_to: '',
  },

  fetchAuditLogs: async () => {
    set({ isLoading: true, error: null })
    
    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 800))
      
      const mockLogs = generateMockAuditLogs()
      const { filters } = get()
      
      // Apply filters
      let filteredLogs = mockLogs
      
      if (filters.search) {
        const searchLower = filters.search.toLowerCase()
        filteredLogs = filteredLogs.filter(log => 
          log.event_type.toLowerCase().includes(searchLower) ||
          log.actor_name?.toLowerCase().includes(searchLower) ||
          log.target_name?.toLowerCase().includes(searchLower)
        )
      }
      
      if (filters.event_type) {
        filteredLogs = filteredLogs.filter(log => log.event_type === filters.event_type)
      }
      
      if (filters.actor_type) {
        filteredLogs = filteredLogs.filter(log => log.actor_type === filters.actor_type)
      }
      
      if (filters.target_type) {
        filteredLogs = filteredLogs.filter(log => log.target_type === filters.target_type)
      }
      
      if (filters.date_from) {
        filteredLogs = filteredLogs.filter(log => 
          new Date(log.created_at) >= new Date(filters.date_from)
        )
      }
      
      if (filters.date_to) {
        filteredLogs = filteredLogs.filter(log => 
          new Date(log.created_at) <= new Date(filters.date_to)
        )
      }
      
      set({ 
        auditLogs: filteredLogs,
        stats: mockStats,
        totalLogs: filteredLogs.length,
        totalPages: Math.ceil(filteredLogs.length / 10),
        isLoading: false,
      })
    } catch (error) {
      set({ 
        error: error instanceof Error ? error.message : 'Failed to fetch audit logs',
        isLoading: false,
      })
    }
  },

  setPage: (page: number) => {
    set({ currentPage: page })
  },

  setFilters: (newFilters: Partial<AuditLogsFilters>) => {
    set((state) => ({
      filters: { ...state.filters, ...newFilters },
      currentPage: 1, // Reset to first page when filters change
    }))
  },

  exportLogs: async () => {
    // Mock export functionality
    await new Promise(resolve => setTimeout(resolve, 1000))
    console.log('Exporting audit logs...')
    // In real implementation, this would download a CSV/Excel file
  },
}))
