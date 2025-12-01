import { useEffect, useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { useToast } from '@/components/ui/Toast'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { api } from '@/lib/api'
import {
  SettingsHeader,
  CompanyInfoSection,
  EmailDeliverySection,
  ApiAccessSection,
  SecuritySection,
} from './components'

export default function Settings() {
  const toast = useToast()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [logoUrl, setLogoUrl] = useState<string | null>(null)
  const [profileLogoUrl, setProfileLogoUrl] = useState<string | null>(null)

  const companyName = useSettingsStore(state => state.companyName)
  const primaryHex = useSettingsStore(state => state.primaryHex)
  const contactEmail = useSettingsStore(state => state.contactEmail)
  const contactPhone = useSettingsStore(state => state.contactPhone)
  const address = useSettingsStore(state => state.address)
  const fromEmail = useSettingsStore(state => state.fromEmail)
  const fromName = useSettingsStore(state => state.fromName)
  const apiKeyMasked = useSettingsStore(state => state.apiKeyMasked)
  const lastGenerated = useSettingsStore(state => state.lastGenerated)
  const changePasswordModalOpen = useSettingsStore(state => state.changePasswordModalOpen)
  const setCompanyName = useSettingsStore(state => state.setCompanyName)
  const setPrimaryHex = useSettingsStore(state => state.setPrimaryHex)
  const setContactEmail = useSettingsStore(state => state.setContactEmail)
  const setContactPhone = useSettingsStore(state => state.setContactPhone)
  const setAddress = useSettingsStore(state => state.setAddress)
  const setFromEmail = useSettingsStore(state => state.setFromEmail)
  const setFromName = useSettingsStore(state => state.setFromName)
  const setLastGenerated = useSettingsStore(state => state.setLastGenerated)
  const setChangePasswordModalOpen = useSettingsStore(state => state.setChangePasswordModalOpen)
  const isValidHex = useSettingsStore(state => state.isValidHex)

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        setLoading(true)
        const response = await api.get<any>('/company/settings')
        
        if (response && response.data) {
          const data = response.data
          setCompanyName(data.name || '')
          setContactEmail(data.contact_email || '')
          setContactPhone(data.contact_phone || '')
          setAddress(data.address || '')
          
          // Load branding from settings
          if (data.settings?.white_label) {
            const branding = data.settings.white_label
            setPrimaryHex(branding.primary_color || '#3b82f6')
            setFromEmail(branding.email_from_address || '')
            setFromName(branding.email_from_name || '')
          }
        }
      } catch (error: any) {
        console.error('Failed to load company settings:', error)
        toast.error('Error', error.message || 'Failed to load company settings')
      } finally {
        setLoading(false)
      }
    }

    fetchSettings()
  }, [])

  const handleSaveBranding = async () => {
    if (!isValidHex()) return
    
    try {
      setSaving(true)
      
      // Save company info
      await api.patch('/company/settings', {
        name: companyName,
        contact_email: contactEmail,
        contact_phone: contactPhone,
        address: address,
      })
      
      // Save branding
      await api.patch('/company/branding', {
        company_name: companyName,
        primary_color: primaryHex,
      })
      
      toast.success('Saved', 'Company information updated successfully')
    } catch (error: any) {
      console.error('Failed to save settings:', error)
      toast.error('Error', error.message || 'Failed to save settings')
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
          contactEmail={contactEmail}
          contactPhone={contactPhone}
          address={address}
          isValidHex={isValidHex()}
          onCompanyNameChange={setCompanyName}
          onPrimaryHexChange={setPrimaryHex}
          onContactEmailChange={setContactEmail}
          onContactPhoneChange={setContactPhone}
          onAddressChange={setAddress}
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
    </FloatingLayout>
  )
}
