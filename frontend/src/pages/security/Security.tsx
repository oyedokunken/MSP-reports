import { useState } from 'react'
import { ChangePasswordModal } from '@/components/modals/ChangePasswordModal'
import { useToast } from '@/components/ui/Toast'
import { Button } from '@/components/ui/button'
import { Shield, Laptop, Smartphone, Globe } from 'lucide-react'
import { useAuth } from '@/contexts/AuthContext'

export default function Security() {
  const toast = useToast()
  const { user } = useAuth()
  const [changePasswordModalOpen, setChangePasswordModalOpen] = useState(false)

  const getDeviceDetails = () => {
    const ua = navigator.userAgent
    let icon = Laptop
    let deviceType = "Desktop"
    
    if (/mobile|android|iphone|ipad|ipod/i.test(ua)) {
      icon = Smartphone
      deviceType = "Mobile Device"
    }

    let os = "Unknown OS"
    if (ua.indexOf("Win") !== -1) os = "Windows"
    if (ua.indexOf("Mac") !== -1) os = "macOS"
    if (ua.indexOf("Linux") !== -1) os = "Linux"
    if (ua.indexOf("Android") !== -1) os = "Android"
    if (ua.indexOf("like Mac") !== -1) os = "iOS"

    let browser = "Unknown Browser"
    if (ua.indexOf("Firefox") > -1) browser = "Firefox"
    else if (ua.indexOf("SamsungBrowser") > -1) browser = "Samsung Internet"
    else if (ua.indexOf("Opera") > -1 || ua.indexOf("OPR") > -1) browser = "Opera"
    else if (ua.indexOf("Trident") > -1) browser = "Internet Explorer"
    else if (ua.indexOf("Edge") > -1) browser = "Microsoft Edge"
    else if (ua.indexOf("Chrome") > -1) browser = "Chrome"
    else if (ua.indexOf("Safari") > -1) browser = "Safari"

    return { icon, deviceType, os, browser }
  }

  const { icon: DeviceIcon, os, browser } = getDeviceDetails()

  return (
    <>
      <div className="space-y-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
              <Shield className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-foreground">Security</h1>
              <p className="text-sm text-muted-foreground">
                Manage your account security settings
              </p>
            </div>
          </div>
        </div>

        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Password</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Change your password to keep your account secure
          </p>

          <div className="mt-4">
            <Button
              onClick={() => setChangePasswordModalOpen(true)}
              variant="outline"
            >
              Change Password
            </Button>
          </div>
        </section>

        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
          <h2 className="text-base font-semibold text-foreground">Device Logged In</h2>
          <p className="mt-1 text-sm text-muted-foreground">Manage your active details</p>

          <div className="mt-6 space-y-4">
            <div className="flex items-center justify-between p-4 rounded-lg bg-muted/30 border">
              <div className="flex items-center gap-4">
                <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                  <DeviceIcon className="h-5 w-5 text-primary" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <p className="font-medium text-sm">{os} • {browser}</p>
                    <span className="px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-[10px] font-bold uppercase tracking-wide">
                      Current Session
                    </span>
                  </div>
                  <div className="flex items-center gap-1.5 mt-1 text-xs text-muted-foreground">
                    <Globe className="h-3 w-3" />
                    <span>
                      Last active: {user?.last_login ? new Date(user.last_login).toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric', 
                        hour: 'numeric', 
                        minute: 'numeric', 
                        hour12: true 
                      }) : 'Just now'}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <ChangePasswordModal
        open={changePasswordModalOpen}
        onOpenChange={setChangePasswordModalOpen}
        onSuccess={() => {
          toast.success('Password Changed', 'Your password has been updated successfully')
        }}
      />
    </>
  )
}
