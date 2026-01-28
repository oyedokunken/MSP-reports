import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { logger } from '@/lib/logger'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { useToast } from '@/components/ui/Toast'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { useBranding } from '@/contexts/BrandingContext'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'
import { api } from '@/lib/api'
import type { CompanySettingsResponse } from '@/types/api/settings'
import type { WhiteLabelSettings } from '@/types/auth'
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
  ReportNamingSection,
} from './components'



export default function Settings() {
  const toast = useToast()
  const navigate = useNavigate()
  const { user, logout } = useAuth()
  const { branding, setBranding } = useBranding()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [showLogoutWarning, setShowLogoutWarning] = useState(false)

  const companyName = useSettingsStore(state => state.companyName)
  const primaryHex = useSettingsStore(state => state.primaryHex)
  const secondaryHex = useSettingsStore(state => state.secondaryHex)
  const contactEmail = useSettingsStore(state => state.contactEmail)
  const contactPhone = useSettingsStore(state => state.contactPhone)
  const address = useSettingsStore(state => state.address)
  const websiteUrl = useSettingsStore(state => state.websiteUrl)
  const logoUrl = useSettingsStore(state => state.logoUrl)
  const faviconUrl = useSettingsStore(state => state.faviconUrl)
  const fromEmail = useSettingsStore(state => state.fromEmail)
  const fromName = useSettingsStore(state => state.fromName)
  const supportEmail = useSettingsStore(state => state.supportEmail)
  const supportPhone = useSettingsStore(state => state.supportPhone)
  const footerText = useSettingsStore(state => state.footerText)
  const reportHeaderText = useSettingsStore(state => state.reportHeaderText)
  const smtpHost = useSettingsStore(state => state.smtpHost)
  const smtpPort = useSettingsStore(state => state.smtpPort)
  const smtpEncryption = useSettingsStore(state => state.smtpEncryption)
  const smtpUsername = useSettingsStore(state => state.smtpUsername)
  const smtpPassword = useSettingsStore(state => state.smtpPassword)
  const setCompanyName = useSettingsStore(state => state.setCompanyName)
  const setPrimaryHex = useSettingsStore(state => state.setPrimaryHex)
  const setSecondaryHex = useSettingsStore(state => state.setSecondaryHex)
  const setContactEmail = useSettingsStore(state => state.setContactEmail)
  const setContactPhone = useSettingsStore(state => state.setContactPhone)
  const setAddress = useSettingsStore(state => state.setAddress)
  const setWebsiteUrl = useSettingsStore(state => state.setWebsiteUrl)
  const setLogoUrl = useSettingsStore(state => state.setLogoUrl)
  const setFaviconUrl = useSettingsStore(state => state.setFaviconUrl)
  const setFromEmail = useSettingsStore(state => state.setFromEmail)
  const setFromName = useSettingsStore(state => state.setFromName)
  const setSupportEmail = useSettingsStore(state => state.setSupportEmail)
  const setSupportPhone = useSettingsStore(state => state.setSupportPhone)
  const setFooterText = useSettingsStore(state => state.setFooterText)
  const setReportHeaderText = useSettingsStore(state => state.setReportHeaderText)
  const setSmtpHost = useSettingsStore(state => state.setSmtpHost)
  const setSmtpPort = useSettingsStore(state => state.setSmtpPort)
  const setSmtpEncryption = useSettingsStore(state => state.setSmtpEncryption)
  const setSmtpUsername = useSettingsStore(state => state.setSmtpUsername)
  const setSmtpPassword = useSettingsStore(state => state.setSmtpPassword)
  const isValidHex = useSettingsStore(state => state.isValidHex)
  const isValidSecondaryHex = useSettingsStore(state => state.isValidSecondaryHex)



  const fetchSettings = useCallback(async () => {
    try {
      setLoading(true)
      const response = await api.get<CompanySettingsResponse>('/company/settings')

      if (response.data) {
        const data = response.data
        setCompanyName(data.name || '')
        setContactEmail(data.contact_email || '')
        setContactPhone(data.contact_phone || '')
        setAddress(data.address || '')

        if (data.settings?.white_label) {
          const branding = data.settings.white_label
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
          setWebsiteUrl(branding.website_url || '')
        }

        if (data.settings?.mail_settings?.smtp) {
          const smtp = data.settings.mail_settings.smtp
          setSmtpHost(smtp.host || '')
          setSmtpPort(smtp.port || 587)
          setSmtpEncryption(smtp.encryption || 'tls')
          setSmtpUsername(smtp.username || '')
        }
      }
    } catch (error) {
      logger.error('❌ Failed to load company settings:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to load company settings'
      toast.error('Error', errorMessage)
    } finally {
      setLoading(false)
    }
  }, [
    toast,
    setAddress,
    setCompanyName,
    setContactEmail,
    setContactPhone,
    setFaviconUrl,
    setFooterText,
    setFromEmail,
    setFromName,
    setLogoUrl,
    setPrimaryHex,
    setReportHeaderText,
    setSecondaryHex,
    setSupportEmail,
    setSupportPhone,
    setWebsiteUrl,
    setSmtpHost,
    setSmtpPort,
    setSmtpEncryption,
    setSmtpUsername
  ])

  useEffect(() => {
    fetchSettings()
  }, [fetchSettings])

  const handleSaveBranding = async () => {
    if (!hasPermission(user, 'company_settings', 'edit')) {
      toast.error('Permission Denied', 'You don\'t have permission to edit company settings')
      return
    }

    if (!isValidHex() || !isValidSecondaryHex()) {
      toast.error('Invalid Color', 'Please enter valid hex colors')
      return
    }

    try {
      setSaving(true)

      await api.patch('/company/settings', {
        name: companyName,
        contact_email: contactEmail,
        contact_phone: contactPhone,
        address: address,
      })

      const brandingData = {
        company_name: companyName,
        primary_color: primaryHex,
        secondary_color: secondaryHex,
        logo_url: logoUrl || null,
        favicon_url: faviconUrl || null,
        support_email: supportEmail || null,
        support_phone: supportPhone || null,
        footer_text: footerText || null,
        report_header_text: reportHeaderText || null,
        website_url: websiteUrl || null,
      }

      const response = await api.patch('/company/branding', brandingData)

      const savedBranding = (response as { data?: unknown })?.data || response

      if (savedBranding && typeof savedBranding === 'object') {
        const brandingObj = savedBranding as {
          company_name?: string;
          logo_url?: string;
          primary_color?: string;
          secondary_color?: string;
          favicon_url?: string;
          email_from_name?: string;
          email_from_address?: string;
          support_email?: string;
          support_phone?: string;
          footer_text?: string;
          report_header_text?: string;
        }
        const newBranding = {
          enabled: true,
          company_name: brandingObj.company_name || companyName,
          logo_url: brandingObj.logo_url || null,
          primary_color: brandingObj.primary_color || primaryHex,
          secondary_color: brandingObj.secondary_color || secondaryHex,
          favicon_url: brandingObj.favicon_url || null,
          email_from_name: brandingObj.email_from_name || fromName,
          email_from_address: brandingObj.email_from_address || fromEmail,
          support_email: brandingObj.support_email || '',
          support_phone: brandingObj.support_phone || '',
          footer_text: brandingObj.footer_text || '',
          report_header_text: brandingObj.report_header_text || '',
        }

        setBranding(newBranding)

        const companyStr = localStorage.getItem('company')
        if (companyStr) {
          try {
            const company = JSON.parse(companyStr)
            company.settings = company.settings || {}
            company.settings.white_label = newBranding
            localStorage.setItem('company', JSON.stringify(company))
          } catch (error) {
            logger.debug('Failed to parse stored settings', error)
          }
        }
      }

      toast.success('Saved', 'Branding updated successfully')

      setShowLogoutWarning(true)

      if (branding) {
        setBranding({
          ...branding,
          ...(savedBranding as Partial<WhiteLabelSettings>),
          company_name: companyName,
          primary_color: primaryHex,
        })
      }

      toast.success('Saved', 'Company information updated successfully')
    } catch (error) {
      logger.error('❌ Failed to save settings:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to save settings'
      toast.error('Error', errorMessage)
    } finally {
      setSaving(false)
    }
  }

  const handleSaveEmail = async () => {
    if (!hasPermission(user, 'company_settings', 'edit')) {
      toast.error('Permission Denied', 'You don\'t have permission to edit company settings')
      return
    }

    try {
      setSaving(true)

      await api.patch('/company/branding', {
        email_from_address: fromEmail,
        email_from_name: fromName,
        smtp_host: smtpHost,
        smtp_port: smtpPort,
        smtp_encryption: smtpEncryption,
        smtp_username: smtpUsername,
        smtp_password: smtpPassword,
      })

      toast.success('Saved', 'Email settings updated successfully')
    } catch (error) {
      logger.error('Failed to save email settings:', error)
      const errorMessage = error instanceof Error ? error.message : 'Failed to save email settings'
      toast.error('Error', errorMessage)
    } finally {
      setSaving(false)
    }
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
          websiteUrl={websiteUrl}
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
          onWebsiteUrlChange={setWebsiteUrl}
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
          smtpHost={smtpHost}
          smtpPort={smtpPort}
          smtpEncryption={smtpEncryption}
          smtpUsername={smtpUsername}
          smtpPassword={smtpPassword}
          onFromEmailChange={setFromEmail}
          onFromNameChange={setFromName}
          onSmtpHostChange={setSmtpHost}
          onSmtpPortChange={setSmtpPort}
          onSmtpEncryptionChange={setSmtpEncryption}
          onSmtpUsernameChange={setSmtpUsername}
          onSmtpPasswordChange={setSmtpPassword}
          onSave={handleSaveEmail}
        />

        <ReportNamingSection />
      </div>

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
