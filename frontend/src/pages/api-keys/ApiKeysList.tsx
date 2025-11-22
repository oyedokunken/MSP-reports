import { useState } from 'react'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Plus, Key, Trash2, Copy, Eye, EyeOff, AlertCircle } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { CreateApiKeyModal } from './modals/CreateApiKeyModal'
import { RevokeApiKeyModal } from './modals/RevokeApiKeyModal'
import { useApiKeysStore } from '@/stores/useApiKeysStore'

export default function ApiKeysList() {
  const toast = useToast()
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [revokeModalOpen, setRevokeModalOpen] = useState(false)
  const [selectedKey, setSelectedKey] = useState<any>(null)
  const [visibleKeys, setVisibleKeys] = useState<Set<number>>(new Set())

  const { apiKeys, isLoading, fetchApiKeys } = useApiKeysStore()

  const toggleKeyVisibility = (keyId: number) => {
    setVisibleKeys(prev => {
      const newSet = new Set(prev)
      if (newSet.has(keyId)) {
        newSet.delete(keyId)
      } else {
        newSet.add(keyId)
      }
      return newSet
    })
  }

  const handleRevoke = (key: any) => {
    setSelectedKey(key)
    setRevokeModalOpen(true)
  }

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-AU', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  return (
    <FloatingLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-foreground">API Keys</h1>
            <p className="text-muted-foreground mt-1">
              Manage API keys for programmatic access to the platform
            </p>
          </div>
          <Button onClick={() => setCreateModalOpen(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Create API Key
          </Button>
        </div>

        {/* Security Notice */}
        <Card className="border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/20">
          <CardContent className="pt-6">
            <div className="flex gap-3">
              <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="text-sm font-medium text-amber-900 dark:text-amber-200">
                  Security Best Practices
                </p>
                <ul className="text-sm text-amber-800 dark:text-amber-300 space-y-1">
                  <li>• API keys are shown only once during creation - copy and store them securely</li>
                  <li>• Use IP whitelisting to restrict access from specific addresses</li>
                  <li>• Grant minimum required permissions for each key</li>
                  <li>• Rotate keys regularly (every 6-12 months)</li>
                  <li>• Revoke unused or compromised keys immediately</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* API Keys List */}
        <div className="space-y-4">
          {isLoading ? (
            <Card>
              <CardContent className="py-12 text-center text-muted-foreground">
                Loading API keys...
              </CardContent>
            </Card>
          ) : apiKeys.length === 0 ? (
            <Card>
              <CardContent className="py-12 text-center">
                <Key className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <h3 className="text-lg font-semibold text-foreground mb-2">No API Keys</h3>
                <p className="text-muted-foreground mb-4">
                  Create your first API key to enable programmatic access
                </p>
                <Button onClick={() => setCreateModalOpen(true)}>
                  <Plus className="h-4 w-4 mr-2" />
                  Create API Key
                </Button>
              </CardContent>
            </Card>
          ) : (
            apiKeys.map((apiKey) => (
              <Card key={apiKey.api_key_id}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="space-y-1">
                      <CardTitle className="flex items-center gap-2">
                        <Key className="h-5 w-5" />
                        {apiKey.display_name}
                      </CardTitle>
                      <CardDescription>
                        Created {formatDate(apiKey.created_at)}
                        {apiKey.last_used_at && (
                          <> • Last used {formatDate(apiKey.last_used_at)}</>
                        )}
                      </CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                      {apiKey.revoked ? (
                        <Badge variant="destructive">Revoked</Badge>
                      ) : (
                        <Badge variant="default">Active</Badge>
                      )}
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* API Key Display */}
                  <div>
                    <label className="text-sm font-medium text-foreground mb-2 block">
                      API Key
                    </label>
                    <div className="flex gap-2">
                      <div className="flex-1 font-mono text-sm bg-muted px-3 py-2 rounded-md border">
                        {visibleKeys.has(apiKey.api_key_id)
                          ? apiKey.key_preview || '••••••••••••••••••••••••••••••••'
                          : '••••••••••••••••••••••••••••••••'}
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => toggleKeyVisibility(apiKey.api_key_id)}
                      >
                        {visibleKeys.has(apiKey.api_key_id) ? (
                          <EyeOff className="h-4 w-4" />
                        ) : (
                          <Eye className="h-4 w-4" />
                        )}
                      </Button>
                      {apiKey.key_preview && (
                        <Button
                          variant="outline"
                          size="sm"
                          
                        >
                          <Copy className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                  </div>

                  {/* Permissions */}
                  {apiKey.permissions && (
                    <div>
                      <label className="text-sm font-medium text-foreground mb-2 block">
                        Permissions
                      </label>
                      <div className="flex flex-wrap gap-2">
                        {Object.entries(apiKey.permissions).map(([key, perms]: [string, any]) =>
                          Object.entries(perms || {}).map(([action, allowed]) =>
                            allowed ? (
                              <Badge key={`${key}-${action}`} variant="secondary">
                                {key}:{action}
                              </Badge>
                            ) : null
                          )
                        )}
                      </div>
                    </div>
                  )}

                  {/* IP Whitelist */}
                  {apiKey.ip_whitelist && apiKey.ip_whitelist.length > 0 && (
                    <div>
                      <label className="text-sm font-medium text-foreground mb-2 block">
                        IP Whitelist
                      </label>
                      <div className="flex flex-wrap gap-2">
                        {apiKey.ip_whitelist.map((ip: string) => (
                          <Badge key={ip} variant="outline">
                            {ip}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Actions */}
                  {!apiKey.revoked && (
                    <div className="flex justify-end pt-2">
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => handleRevoke(apiKey)}
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Revoke Key
                      </Button>
                    </div>
                  )}
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>

      {/* Modals */}
      <CreateApiKeyModal
        open={createModalOpen}
        onOpenChange={setCreateModalOpen}
        onSuccess={() => {
          fetchApiKeys()
          toast.success('API Key Created', 'Your new API key has been created successfully')
        }}
      />

      {selectedKey && (
        <RevokeApiKeyModal
          open={revokeModalOpen}
          onOpenChange={setRevokeModalOpen}
          apiKey={selectedKey}
          onSuccess={() => {
            fetchApiKeys()
            toast.success('API Key Revoked', 'The API key has been revoked successfully')
          }}
        />
      )}
    </FloatingLayout>
  )
}
