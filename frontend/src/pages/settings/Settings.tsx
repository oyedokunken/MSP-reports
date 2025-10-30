import FloatingLayout from '@/components/layout/FloatingLayout'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { useToast } from '@/components/ui/Toast'
import { useSettingsStore } from '@/stores/useSettingsStore'
import {
  SettingsHeader,
  CompanyInfoSection,
  EmailDeliverySection,
  ApiAccessSection,
  SecuritySection,
} from './components'

export default function Settings() {
  const toast = useToast()

  const companyName = useSettingsStore(state => state.companyName)
  const primaryHex = useSettingsStore(state => state.primaryHex)
  const fromEmail = useSettingsStore(state => state.fromEmail)
  const fromName = useSettingsStore(state => state.fromName)
  const apiKeyMasked = useSettingsStore(state => state.apiKeyMasked)
  const lastGenerated = useSettingsStore(state => state.lastGenerated)
  const changePasswordModalOpen = useSettingsStore(state => state.changePasswordModalOpen)
  const setCompanyName = useSettingsStore(state => state.setCompanyName)
  const setPrimaryHex = useSettingsStore(state => state.setPrimaryHex)
  const setFromEmail = useSettingsStore(state => state.setFromEmail)
  const setFromName = useSettingsStore(state => state.setFromName)
  const setLastGenerated = useSettingsStore(state => state.setLastGenerated)
  const setChangePasswordModalOpen = useSettingsStore(state => state.setChangePasswordModalOpen)
  const isValidHex = useSettingsStore(state => state.isValidHex)

  const handleSaveBranding = () => {
    if (!isValidHex()) return
    toast.success('Saved', 'Company information updated successfully')
  }

  const handleSaveEmail = () => {
    toast.success('Saved', 'Email settings updated successfully')
  }

  const handleRegenerateKey = () => {
    const random = Array.from(crypto.getRandomValues(new Uint8Array(32)))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
    setLastGenerated(random)
    alert('New API key generated. Copy it now; it will only be shown once.\n\n' + random)
  }

  return (
    <FloatingLayout>
      <div className="space-y-8">
        <SettingsHeader />

        <CompanyInfoSection
          companyName={companyName}
          primaryHex={primaryHex}
          isValidHex={isValidHex()}
          onCompanyNameChange={setCompanyName}
          onPrimaryHexChange={setPrimaryHex}
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
