export interface CompanyBranding {
  enabled: boolean
  company_name: string
  logo_url: string | null
  primary_color: string
  secondary_color: string
  favicon_url: string | null
  email_from_name: string
  email_from_address: string
  support_email: string
  support_phone: string | null
  footer_text: string
  report_header_text: string
}

export interface BrandingState {
  branding: CompanyBranding | null
  isLoading: boolean
}
