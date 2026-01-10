import { useState, useRef, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Upload, X, Image as ImageIcon } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import apiClient from '@/lib/api'

interface LogoUploadProps {
  type: 'company' | 'profile'
  currentLogoUrl: string | null
  onUploadSuccess: (logoUrl: string) => void
  onDeleteSuccess: () => void
}

export function LogoUpload({ type, currentLogoUrl, onUploadSuccess, onDeleteSuccess }: LogoUploadProps) {
  const [uploading, setUploading] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [preview, setPreview] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const toast = useToast()

  const maxSize = type === 'company' ? '800x800' : '400x400'
  const maxFileSizeMB = 2
  const title = type === 'company' ? 'Company Logo' : 'Profile Logo'

  // Clear preview when currentLogoUrl updates (after successful upload)
  useEffect(() => {
    if (currentLogoUrl && preview) {
      setPreview(null)
    }
  }, [currentLogoUrl, preview])

  const validateImage = (file: File): Promise<boolean> => {
    return new Promise((resolve) => {
      // Check file size (2MB max)
      if (file.size > maxFileSizeMB * 1024 * 1024) {
        toast.error('File too large', `Maximum file size is ${maxFileSizeMB}MB`)
        resolve(false)
        return
      }

      // Check file type
      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml']
      if (!validTypes.includes(file.type)) {
        toast.error('Invalid format', 'Only JPEG, PNG, and SVG images are allowed')
        resolve(false)
        return
      }

      // Check dimensions (for non-SVG images)
      if (file.type !== 'image/svg+xml') {
        const img = new Image()
        img.onload = () => {
          const maxDimension = type === 'company' ? 800 : 400
          if (img.width > maxDimension || img.height > maxDimension) {
            toast.error('Image too large', `Maximum dimensions are ${maxSize} pixels`)
            resolve(false)
          } else {
            resolve(true)
          }
        }
        img.onerror = () => {
          toast.error('Invalid image', 'Failed to load image')
          resolve(false)
        }
        img.src = URL.createObjectURL(file)
      } else {
        resolve(true)
      }
    })
  }

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    // Validate image
    const isValid = await validateImage(file)
    if (!isValid) {
      e.target.value = '' // Reset file input
      return
    }

    // Show preview
    const reader = new FileReader()
    reader.onloadend = () => {
      setPreview(reader.result as string)
    }
    reader.readAsDataURL(file)

    // Upload immediately
    await handleUpload(file)
    e.target.value = '' // Reset file input
  }

  const handleUpload = async (file: File) => {
    try {
      setUploading(true)

      const formData = new FormData()
      formData.append('logo', file)
      formData.append('type', type)

      const response = await apiClient.post<unknown>('/company/logo/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      const data = response.data as { status?: string; message?: string; data?: { logo_url?: string } }
      if (data.status === 'success') {
        toast.success('Upload Successful', data.message || 'Logo uploaded successfully')
        if (data.data?.logo_url) {
          onUploadSuccess(data.data.logo_url)
        }
        // Don't clear preview immediately - let the currentLogoUrl prop update first
        // The preview will be replaced by currentLogoUrl on next render
      } else {
        toast.error('Upload Failed', data.message || 'Failed to upload logo')
      }
    } catch (error: unknown) {
      console.error('Upload error:', error)
      const err = error as { response?: { data?: { message?: string; errors?: { logo?: string[] } } }; message?: string }
      const errorMessage = err.response?.data?.message
        || err.response?.data?.errors?.logo?.[0]
        || err.message
        || 'Failed to upload logo'
      toast.error('Upload Failed', errorMessage)
    } finally {
      setUploading(false)
    }
  }

  const handleDelete = async () => {
    if (!confirm(`Are you sure you want to delete the ${title.toLowerCase()}?`)) {
      return
    }

    try {
      setDeleting(true)

      const response = await apiClient.delete<unknown>('/company/logo/delete', {
        data: { type }
      })

      const data = response.data as { status?: string; message?: string }
      if (data.status === 'success') {
        toast.success('Delete Successful', data.message || 'Logo deleted successfully')
        onDeleteSuccess()
        setPreview(null)
      } else {
        toast.error('Delete Failed', data.message || 'Failed to delete logo')
      }
    } catch (error: unknown) {
      console.error('Delete error:', error)
      const err = error as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = err.response?.data?.message || err.message || 'Failed to delete logo'
      toast.error('Delete Failed', errorMessage)
    } finally {
      setDeleting(false)
    }
  }

  const displayImageUrl = preview || currentLogoUrl

  return (
    <div className="space-y-3">
      <label className="block text-sm font-medium text-foreground">{title}</label>

      <div className="flex items-start gap-4">
        {/* Image Preview */}
        <div className="flex-shrink-0">
          <div className="w-32 h-32 border-2 border-dashed border-border rounded-lg flex items-center justify-center bg-muted/20 overflow-hidden">
            {displayImageUrl ? (
              <img
                src={displayImageUrl}
                alt={title}
                className="w-full h-full object-contain"
              />
            ) : (
              <ImageIcon className="h-12 w-12 text-muted-foreground" />
            )}
          </div>
        </div>

        {/* Upload Controls */}
        <div className="flex-1 space-y-2">
          <input
            ref={fileInputRef}
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/svg+xml"
            onChange={handleFileSelect}
            className="hidden"
          />

          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading || deleting}
            >
              <Upload className="h-4 w-4 mr-2" />
              {uploading ? 'Uploading...' : currentLogoUrl ? 'Change Logo' : 'Upload Logo'}
            </Button>

            {currentLogoUrl && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handleDelete}
                disabled={uploading || deleting}
              >
                <X className="h-4 w-4 mr-2" />
                {deleting ? 'Deleting...' : 'Remove'}
              </Button>
            )}
          </div>

          <p className="text-xs text-muted-foreground">
            Max size: {maxSize} pixels, {maxFileSizeMB}MB<br />
            Formats: JPEG, PNG, SVG
          </p>
        </div>
      </div>
    </div>
  )
}
