import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, XCircle, Package, Database, Users, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'

interface CheckResult {
  allowed: boolean
  reason: string | null
}

export function LicenseCheckCard() {
  const toast = useToast()
  const [integrationType, setIntegrationType] = useState('datto')
  const [retentionMonths, setRetentionMonths] = useState('36')
  
  const [integrationResult, setIntegrationResult] = useState<CheckResult | null>(null)
  const [retentionResult, setRetentionResult] = useState<CheckResult | null>(null)
  const [userResult, setUserResult] = useState<any | null>(null)
  
  const [checkingIntegration, setCheckingIntegration] = useState(false)
  const [checkingRetention, setCheckingRetention] = useState(false)
  const [checkingUser, setCheckingUser] = useState(false)

  const checkIntegrationLicense = async () => {
    setCheckingIntegration(true)
    try {
      const response = await api.post('/licenses/check/integration', {
        integration_type: integrationType
      })
      const data = (response as any).data || response
      const result = data.data || data
      
      if (!result || typeof result.allowed === 'undefined') {
        throw new Error('Invalid response from server')
      }
      
      setIntegrationResult(result)
      
      if (result.allowed) {
        toast.success('License Check', `${integrationType} integration can be activated`)
      } else {
        toast.error('License Check Failed', result.reason || 'Cannot activate integration')
      }
    } catch (err: any) {
      console.error('Integration license check error:', err)
      toast.error('Check Failed', err.response?.data?.message || err.message)
    } finally {
      setCheckingIntegration(false)
    }
  }

  const checkRetentionLicense = async () => {
    setCheckingRetention(true)
    try {
      const response = await api.post('/licenses/check/retention', {
        requested_months: parseInt(retentionMonths)
      })
      const data = (response as any).data || response
      const result = data.data || data
      
      if (!result || typeof result.allowed === 'undefined') {
        throw new Error('Invalid response from server')
      }
      
      setRetentionResult(result)
      
      if (result.allowed) {
        toast.success('License Check', `${retentionMonths} months retention is allowed`)
      } else {
        toast.error('License Check Failed', result.reason || 'Cannot extend retention')
      }
    } catch (err: any) {
      console.error('Retention license check error:', err)
      toast.error('Check Failed', err.response?.data?.message || err.message)
    } finally {
      setCheckingRetention(false)
    }
  }

  const checkUserLicense = async () => {
    setCheckingUser(true)
    try {
      const response = await api.post('/licenses/check/user', {})
      const data = (response as any).data || response
      const result = data.data || data
      
      if (!result || !result.can_create) {
        throw new Error('Invalid response from server')
      }
      
      setUserResult(result)
      
      if (result.can_create.allowed) {
        toast.success('License Check', 'New user can be created')
      } else {
        toast.error('License Check Failed', result.can_create.reason || 'Cannot create user')
      }
    } catch (err: any) {
      console.error('User license check error:', err)
      toast.error('Check Failed', err.response?.data?.message || err.message)
    } finally {
      setCheckingUser(false)
    }
  }

  const ResultBadge = ({ result }: { result: CheckResult | null }) => {
    if (!result) return null
    
    return (
      <div className="flex items-center gap-2 mt-3">
        {result.allowed ? (
          <>
            <CheckCircle className="h-4 w-4 text-green-500" />
            <Badge variant="default" className="bg-green-500">Allowed</Badge>
          </>
        ) : (
          <>
            <XCircle className="h-4 w-4 text-red-500" />
            <Badge variant="destructive">Not Allowed</Badge>
          </>
        )}
        {result.reason && (
          <span className="text-sm text-muted-foreground ml-2">{result.reason}</span>
        )}
      </div>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>License Checks</CardTitle>
        <CardDescription>
          Test license permissions for integrations, retention, and users
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Integration Check */}
          <div className="space-y-3">
            <div className="flex items-center gap-2 mb-3">
              <Package className="h-5 w-5 text-primary" />
              <h3 className="font-semibold">Integration</h3>
            </div>
            <div className="space-y-2">
              <Label htmlFor="integration-type">Integration Type</Label>
              <Input
                id="integration-type"
                value={integrationType}
                onChange={(e) => setIntegrationType(e.target.value)}
                placeholder="e.g., datto, autotask"
              />
            </div>
            <Button
              onClick={checkIntegrationLicense}
              disabled={checkingIntegration || !integrationType}
              className="w-full"
              size="sm"
            >
              {checkingIntegration ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            <ResultBadge result={integrationResult} />
          </div>

          {/* Retention Check */}
          <div className="space-y-3">
            <div className="flex items-center gap-2 mb-3">
              <Database className="h-5 w-5 text-primary" />
              <h3 className="font-semibold">Retention</h3>
            </div>
            <div className="space-y-2">
              <Label htmlFor="retention-months">Months</Label>
              <Input
                id="retention-months"
                type="number"
                value={retentionMonths}
                onChange={(e) => setRetentionMonths(e.target.value)}
                placeholder="e.g., 36"
                min="1"
              />
            </div>
            <Button
              onClick={checkRetentionLicense}
              disabled={checkingRetention || !retentionMonths}
              className="w-full"
              size="sm"
            >
              {checkingRetention ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            <ResultBadge result={retentionResult} />
          </div>

          {/* User Check */}
          <div className="space-y-3">
            <div className="flex items-center gap-2 mb-3">
              <Users className="h-5 w-5 text-primary" />
              <h3 className="font-semibold">User Creation</h3>
            </div>
            <div className="space-y-2">
              <Label>Check if new user can be created</Label>
              <p className="text-xs text-muted-foreground">
                Validates against current user license quota
              </p>
            </div>
            <Button
              onClick={checkUserLicense}
              disabled={checkingUser}
              className="w-full"
              size="sm"
            >
              {checkingUser ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            {userResult && (
              <div className="flex items-center gap-2 mt-3">
                {userResult.can_create.allowed ? (
                  <>
                    <CheckCircle className="h-4 w-4 text-green-500" />
                    <Badge variant="default" className="bg-green-500">Can Create</Badge>
                  </>
                ) : (
                  <>
                    <XCircle className="h-4 w-4 text-red-500" />
                    <Badge variant="destructive">Cannot Create</Badge>
                  </>
                )}
                {userResult.can_create.reason && (
                  <span className="text-sm text-muted-foreground ml-2">
                    {userResult.can_create.reason}
                  </span>
                )}
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
