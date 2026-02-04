import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Plus, Key, Trash2, Copy, Eye, EyeOff, AlertCircle } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import { CreateApiKeyModal } from './modals/CreateApiKeyModal'
import { RevokeApiKeyModal } from './modals/RevokeApiKeyModal'
import { useApiKeysStore, type ApiKey } from '@/stores/useApiKeysStore'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

export default function ApiKeysList() {
  const toast = useToast()
  const [createModalOpen, setCreateModalOpen] = useState(false)
  const [revokeModalOpen, setRevokeModalOpen] = useState(false)
  const [selectedKey, setSelectedKey] = useState<ApiKey | null>(null)
  const { apiKeys, isLoading, fetchApiKeys } = useApiKeysStore()
  const [visibleKeys, setVisibleKeys] = useState<Set<number>>(new Set())

  useEffect(() => {
    fetchApiKeys()
  }, [fetchApiKeys])

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

  const handleRevoke = (key: ApiKey) => {
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

  const handleCopy = async (text: string) => {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text)
      } else {
        const textArea = document.createElement("textarea")
        textArea.value = text
        textArea.style.position = "fixed"
        textArea.style.left = "-999999px"
        textArea.style.top = "-999999px"
        document.body.appendChild(textArea)
        textArea.focus()
        textArea.select()
        document.execCommand('copy')
        textArea.remove()
      }
      toast.success('Copied', 'API Key preview copied to clipboard')
    } catch (err) {
      console.error('Failed to copy:', err)
      toast.error('Copy Failed', 'Please try selecting the text and copying manually')
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-foreground">API Keys</h1>
          <p className="text-muted-foreground mt-1">
            Manage API keys for programmatic access to the platform
          </p>
        </div>
      </div>

      <div className="max-w-4xl mx-auto w-full space-y-6">
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
            <div className="space-y-4">
              <div className="flex justify-end">
                <Button onClick={() => setCreateModalOpen(true)} size="sm">
                  <Plus className="h-4 w-4 mr-2" />
                  Create API Key
                </Button>
              </div>
              <Card className="!p-0 overflow-hidden border-border/50 shadow-sm">
                <Table>
                <TableHeader className="bg-muted/20 border-b-0">
                  <TableRow className="hover:bg-transparent !border-b-0">
                    <TableHead className="!h-auto !py-3 text-sm font-semibold w-[250px]">Name</TableHead>
                    <TableHead className="!h-auto !py-3 text-sm font-semibold w-[300px]">API Key Preview</TableHead>
                    <TableHead className="!h-auto !py-3 text-sm font-semibold">Status</TableHead>
                    <TableHead className="!h-auto !py-3 text-sm font-semibold">Permissions</TableHead>
                    <TableHead className="!h-auto !py-3 text-sm font-semibold text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {apiKeys.map((apiKey) => (
                    <TableRow key={apiKey.api_key_id} className="group transition-colors">
                      <TableCell className="py-2.5">
                        <div className="flex flex-col gap-0.5">
                          <span className="font-semibold text-foreground flex items-center gap-1.5 text-sm">
                            <Key className="h-4 w-4 text-primary/70" />
                            {apiKey.display_name}
                          </span>
                          <span className="text-xs text-muted-foreground flex items-center gap-1">
                            Added {formatDate(apiKey.created_at)}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="py-2.5">
                        <div className="flex items-center gap-2">
                          <code className="bg-muted/50 px-2 py-1 rounded text-xs font-mono border border-border/50 min-w-[140px]">
                            {visibleKeys.has(apiKey.api_key_id)
                              ? apiKey.key_preview || '••••••••••••'
                              : '••••••••••••'}
                          </code>
                          <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button
                              variant="ghost"
                              size="icon"
                              className="h-7 w-7"
                              onClick={() => toggleKeyVisibility(apiKey.api_key_id)}
                            >
                              {visibleKeys.has(apiKey.api_key_id) ? (
                                <EyeOff className="h-3.5 w-3.5" />
                              ) : (
                                <Eye className="h-3.5 w-3.5" />
                              )}
                            </Button>
                            {apiKey.key_preview && (
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                onClick={() => handleCopy(apiKey.key_preview!)}
                              >
                                <Copy className="h-3.5 w-3.5" />
                              </Button>
                            )}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell className="py-2.5">
                        {apiKey.revoked ? (
                          <Badge variant="destructive" className="px-2 py-0.5 text-xs font-medium">
                            Revoked
                          </Badge>
                        ) : (
                          <Badge className="px-2 py-0.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white border-none">
                            Active
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="py-2.5">
                        <div className="flex flex-wrap gap-1 max-w-[200px]">
                          {apiKey.permissions && Object.entries(apiKey.permissions).map(([key, perms]) =>
                            Object.entries(perms || {}).map(([action, allowed]) =>
                              allowed ? (
                                <Badge
                                  key={`${key}-${action}`}
                                  className="bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 transition-colors text-xs py-0 px-2 leading-5 h-5"
                                >
                                  {key.substring(0, 1).toUpperCase()}:{action}
                                </Badge>
                              ) : null
                            )
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="py-2.5 text-right">
                        {!apiKey.revoked && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                            onClick={() => handleRevoke(apiKey)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              </Card>
            </div>
          )}
        </div>
        
        <Card className="border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/20">
          <CardContent>
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
      </div>

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
      </div>
    )
}
