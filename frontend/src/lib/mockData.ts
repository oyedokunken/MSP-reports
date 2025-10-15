import type { User, LoginResponse } from '@/types/auth'
import type { CompanyBranding } from '@/types/branding'

// Hardcoded test credentials
export const MOCK_CREDENTIALS = {
  email: 'admin@example.com',
  password: 'password123',
}

// Mock user data
export const MOCK_USER: User = {
  id: 1,
  name: 'John Doe',
  email: 'admin@example.com',
  company_id: 1,
  company_name: 'Territory Technology Solutions',
  permissions: {
    integrations: {
      view: true,
      manage: true,
      sync: true,
    },
    customers: {
      view: true,
      edit: true,
      map: true,
    },
    reports: {
      view: true,
      configure: true,
      generate: true,
      download: true,
    },
    users: {
      view: true,
      manage: true,
    },
    company_settings: {
      view: true,
      edit: true,
    },
  },
  last_login: new Date().toISOString(),
  created_at: '2025-01-01T00:00:00Z',
}

// Mock login response
export const MOCK_LOGIN_RESPONSE: LoginResponse = {
  user: MOCK_USER,
  token: 'mock-jwt-token-12345',
  expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(), // 24 hours
}

// Mock company branding
export const MOCK_BRANDING: CompanyBranding = {
  enabled: true,
  company_name: 'Territory Technology Solutions',
  logo_url: null,
  primary_color: '#1e40af',
  secondary_color: '#7c3aed',
  favicon_url: null,
  email_from_name: 'TTS Reports',
  email_from_address: 'reports@tts.com.au',
  support_email: 'support@tts.com.au',
  support_phone: '1300 123 456',
  footer_text: '© 2025 Territory Technology Solutions',
  report_header_text: 'Monthly Service Report',
}

// Mock authentication function
export async function mockLogin(
  email: string,
  password: string
): Promise<LoginResponse> {
  // Simulate API delay
  await new Promise((resolve) => setTimeout(resolve, 800))

  // Check credentials
  if (email === MOCK_CREDENTIALS.email && password === MOCK_CREDENTIALS.password) {
    return MOCK_LOGIN_RESPONSE
  }

  // Alternative test accounts
  const testAccounts = [
    { email: 'user@test.com', password: 'test123' },
    { email: 'demo@demo.com', password: 'demo' },
  ]

  const isValidTestAccount = testAccounts.some(
    (account) => account.email === email && account.password === password
  )

  if (isValidTestAccount) {
    return {
      ...MOCK_LOGIN_RESPONSE,
      user: {
        ...MOCK_USER,
        email,
        name: email.split('@')[0].charAt(0).toUpperCase() + email.split('@')[0].slice(1),
      },
    }
  }

  throw new Error('Invalid credentials')
}

// Mock branding fetch
export async function mockFetchBranding(): Promise<CompanyBranding> {
  await new Promise((resolve) => setTimeout(resolve, 500))
  return MOCK_BRANDING
}
