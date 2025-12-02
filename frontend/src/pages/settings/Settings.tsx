import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { useToast } from '@/components/ui/Toast'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { useBranding } from '@/contexts/BrandingContext'
import { useAuth } from '@/contexts/AuthContext'
import { api } from '@/lib/api'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { AlertTriangle } from 'lucide-react'
import {
  SettingsHeader,
  CompanyInfoSection,
  EmailDeliverySection,
  ApiAccessSection,
  SecuritySection,
} from './components'

export default function Settings() {
  const toast = useToast()
  const navigate = useNavigate()
  const { setBranding } = useBranding()
  const { logout } = useAuth()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [showLogoutWarning, setShowLogoutWarning] = useState(false)

  const companyName = useSettingsStore(state => state.companyName)
  const primaryHex = useSettingsStore(state => state.primaryHex)
  const secondaryHex = useSettingsStore(state => state.secondaryHex)
  const contactEmail = useSettingsStore(state => state.contactEmail)
  const contactPhone = useSettingsStore(state => state.contactPhone)
  const address = useSettingsStore(state => state.address)
  const logoUrl = useSettingsStore(state => state.logoUrl)
  const faviconUrl = useSettingsStore(state => state.faviconUrl)
  const fromEmail = useSettingsStore(state => state.fromEmail)
  const fromName = useSettingsStore(state => state.fromName)
  const supportEmail = useSettingsStore(state => state.supportEmail)
  const supportPhone = useSettingsStore(state => state.supportPhone)
  const footerText = useSettingsStore(state => state.footerText)
  const reportHeaderText = useSettingsStore(state => state.reportHeaderText)
  const apiKeyMasked = useSettingsStore(state => state.apiKeyMasked)
  const lastGenerated = useSettingsStore(state => state.lastGenerated)
  const changePasswordModalOpen = useSettingsStore(state => state.changePasswordModalOpen)
  const setCompanyName = useSettingsStore(state => state.setCompanyName)
  const setPrimaryHex = useSettingsStore(state => state.setPrimaryHex)
  const setSecondaryHex = useSettingsStore(state => state.setSecondaryHex)
  const setContactEmail = useSettingsStore(state => state.setContactEmail)
  const setContactPhone = useSettingsStore(state => state.setContactPhone)
  const setAddress = useSettingsStore(state => state.setAddress)
  const setLogoUrl = useSettingsStore(state => state.setLogoUrl)
  const setFaviconUrl = useSettingsStore(state => state.setFaviconUrl)
  const setFromEmail = useSettingsStore(state => state.setFromEmail)
  const setFromName = useSettingsStore(state => state.setFromName)
  const setSupportEmail = useSettingsStore(state => state.setSupportEmail)
  const setSupportPhone = useSettingsStore(state => state.setSupportPhone)
  const setFooterText = useSettingsStore(state => state.setFooterText)
  const setReportHeaderText = useSettingsStore(state => state.setReportHeaderText)
  const setLastGenerated = useSettingsStore(state => state.setLastGenerated)
  const setChangePasswordModalOpen = useSettingsStore(state => state.setChangePasswordModalOpen)
  const isValidHex = useSettingsStore(state => state.isValidHex)
  const isValidSecondaryHex = useSettingsStore(state => state.isValidSecondaryHex)

  const fetchSettings = async () => {
    try {
      setLoading(true)
      console.log('📥 Fetching company settings...')
      const response = await api.get<any>('/company/settings')
      
      console.log('📦 Received settings:', response)
      
      if (response && response.data) {
        const data = response.data
        console.log('🏢 Company data:', data)
        
        setCompanyName(data.name || '')
        setContactEmail(data.contact_email || '')
        setContactPhone(data.contact_phone || '')
        setAddress(data.address || '')
        
        // Load branding from settings
        if (data.settings?.white_label) {
          const branding = data.settings.white_label
          console.log('🎨 Branding data:', branding)
          
          setPrimaryHex(branding.primary_color || '#3b82f6')
          setSecondaryHex(branding.secondary_color || '#7c3aed')
          setLogoUrl(branding.logo_url || null)
          setFaviconUrl(branding.favicon_url || null)
          setFromEmail(branding.email_from_address || '')
          setFromName(branding.email_from_name || '')
          setSupportEmail(branding.support_email || '')
          setSupportPhone(branding.support_phone || '')
          setFooterText(branding.footer_text || '')
          setReportHeaderText(branding.report_header_text || 'Monthly Service Report')
          
          console.log('✅ Settings loaded - Secondary color:', branding.secondary_color)
        } else {
          console.warn('⚠️ No white_label settings found in response')
        }
      }
    } catch (error: any) {
      console.error('❌ Failed to load company settings:', error)
      toast.error('Error', error.message || 'Failed to load company settings')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchSettings()
  }, [])

  const handleSaveBranding = async () => {
    if (!isValidHex() || !isValidSecondaryHex()) {
      toast.error('Invalid Color', 'Please enter valid hex colors')
      return
    }
    
    try {
      setSaving(true)
      
      console.log('💾 Starting save process...')
      console.log('📝 Current values:', {
        primaryHex,
        secondaryHex,
        logoUrl,
        supportEmail,
      })
      
      // Save company info
      console.log('1️⃣ Saving company info...')
      await api.patch('/company/settings', {
        name: companyName,
        contact_email: contactEmail,
        contact_phone: contactPhone,
        address: address,
      })
      console.log('✅ Company info saved')
      
      // Save branding (only send non-empty values)
      const brandingData: any = {
        company_name: companyName,
        primary_color: primaryHex,
        secondary_color: secondaryHex,
      }
      
      // Add optional fields only if they have values
      if (logoUrl) brandingData.logo_url = logoUrl
      if (faviconUrl) brandingData.favicon_url = faviconUrl
      if (supportEmail) brandingData.support_email = supportEmail
      if (supportPhone) brandingData.support_phone = supportPhone
      if (footerText) brandingData.footer_text = footerText
      if (reportHeaderText) brandingData.report_header_text = reportHeaderText
      
      console.log('2️⃣ Saving branding data:', brandingData)
      
      const response = await api.patch('/company/branding', brandingData)
      
      console.log('✅ Branding saved! Response:', response)
      
      // Update branding context immediately with new colors
      if (response?.data) {
        console.log('🎨 Updating branding context with:', response.data)
        const newBranding = {
          enabled: true,
          company_name: companyName,
          logo_url: logoUrl,
          primary_color: primaryHex,
          secondary_color: secondaryHex,
          favicon_url: faviconUrl,
          email_from_name: fromName,
          email_from_address: fromEmail,
          support_email: supportEmail,
          support_phone: supportPhone,
          footer_text: footerText,
          report_header_text: reportHeaderText,
        }
        
        setBranding(newBranding)
        
        // Also update localStorage company data so it persists on refresh
        const companyStr = localStorage.getItem('company')
        if (companyStr) {
          try {
            const company = JSON.parse(companyStr)
            company.settings = company.settings || {}
            company.settings.white_label = newBranding
            localStorage.setItem('company', JSON.stringify(company))
            console.log('💾 Updated company in localStorage with new branding')
          } catch (error) {
            console.error('Failed to update localStorage:', error)
          }
        }
      }
      
      toast.success('Saved', 'Branding updated successfully')
      
      // Show logout warning modal
      setShowLogoutWarning(true)
      
    } catch (error: any) {
      console.error('❌ Failed to save settings:', error)
      console.error('❌ Error details:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      })
      toast.error('Error', error.response?.data?.message || error.message || 'Failed to save settings')
    } finally {
      setSaving(false)
    }
  }

  const handleSaveEmail = async () => {
    try {
      setSaving(true)
      
      await api.patch('/company/branding', {
        email_from_address: fromEmail,
        email_from_name: fromName,
      })
      
      toast.success('Saved', 'Email settings updated successfully')
    } catch (error: any) {
      console.error('Failed to save email settings:', error)
      toast.error('Error', error.message || 'Failed to save email settings')
    } finally {
      setSaving(false)
    }
  }

  const handleRegenerateKey = () => {
    const random = Array.from(crypto.getRandomValues(new Uint8Array(32)))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
    setLastGenerated(random)
    alert('New API key generated. Copy it now; it will only be shown once.\n\n' + random)
  }

  if (loading) {
    return (
      <FloatingLayout>
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-muted-foreground">Loading settings...</div>
        </div>
      </FloatingLayout>
    )
  }

  return (
    <FloatingLayout>
      <div className="space-y-8">
        <SettingsHeader />

        <CompanyInfoSection
          companyName={companyName}
          primaryHex={primaryHex}
          secondaryHex={secondaryHex}
          contactEmail={contactEmail}
          contactPhone={contactPhone}
          address={address}
          logoUrl={logoUrl}
          faviconUrl={faviconUrl}
          supportEmail={supportEmail}
          supportPhone={supportPhone}
          footerText={footerText}
          reportHeaderText={reportHeaderText}
          isValidHex={isValidHex()}
          isValidSecondaryHex={isValidSecondaryHex()}
          saving={saving}
          onCompanyNameChange={setCompanyName}
          onPrimaryHexChange={setPrimaryHex}
          onSecondaryHexChange={setSecondaryHex}
          onContactEmailChange={setContactEmail}
          onContactPhoneChange={setContactPhone}
          onAddressChange={setAddress}
          onLogoUrlChange={setLogoUrl}
          onFaviconUrlChange={setFaviconUrl}
          onSupportEmailChange={setSupportEmail}
          onSupportPhoneChange={setSupportPhone}
          onFooterTextChange={setFooterText}
          onReportHeaderTextChange={setReportHeaderText}
          onSave={handleSaveBranding}
        />

        <EmailDeliverySection
          fromEmail={fromEmail}
          fromName={fromName}
          onFromEmailChange={setFromEmail}
          onFromNameChange={setFromName}
          onSave={handleSaveEmail}
        />

        <ApiAccessSection
          apiKeyMasked={apiKeyMasked}
          lastGenerated={lastGenerated}
          onRegenerate={handleRegenerateKey}
        />

        <SecuritySection
          onChangePassword={() => setChangePasswordModalOpen(true)}
        />
      </div>

      {/* Change Password Modal */}
      <ChangePasswordModal
        open={changePasswordModalOpen}
        onOpenChange={setChangePasswordModalOpen}
        onSuccess={() => {
          toast.success('Password Changed', 'Your password has been updated successfully')
        }}
      />

      {/* Logout Warning Modal */}
      <Dialog open={showLogoutWarning} onOpenChange={setShowLogoutWarning}>
        <DialogContent>
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/20">
                <AlertTriangle className="h-6 w-6 text-orange-600 dark:text-orange-500" />
              </div>
              <div>
                <DialogTitle>Logout Required</DialogTitle>
                <DialogDescription className="mt-1">
                  Branding changes will take effect after you log out and log back in.
                </DialogDescription>
              </div>
            </div>
          </DialogHeader>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              onClick={() => setShowLogoutWarning(false)}
            >
              Stay Logged In
            </Button>
            <Button
              onClick={async () => {
                setShowLogoutWarning(false)
                await logout()
                navigate('/login')
              }}
            >
              Logout Now
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </FloatingLayout>
  )
}
