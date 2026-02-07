import { useState, useRef, useCallback, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Upload, Trash2, Image as ImageIcon, Crop, Check, X } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import apiClient from '@/lib/api'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import ReactCrop, { type Crop as CropType, centerCrop, makeAspectCrop } from 'react-image-crop'
import 'react-image-crop/dist/ReactCrop.css'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface LogoUploadProps {
  type: 'company' | 'profile'
  currentLogoUrl: string | null
  onUploadSuccess: (logoUrl: string) => void
  onDeleteSuccess: () => void
  uploadUrl?: string
  deleteUrl?: string
  fileKey?: string
}

function centerAspectCrop(mediaWidth: number, mediaHeight: number, aspect: number) {
  return centerCrop(
    makeAspectCrop({ unit: '%', width: 90 }, aspect, mediaWidth, mediaHeight),
    mediaWidth,
    mediaHeight
  )
}

export function LogoUpload({ 
  type, 
  currentLogoUrl, 
  onUploadSuccess, 
  onDeleteSuccess,
  uploadUrl,
  deleteUrl,
  fileKey = 'logo'
}: LogoUploadProps) {
  const [uploading, setUploading] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [preview, setPreview] = useState<string | null>(null)
  const [showDeleteModal, setShowDeleteModal] = useState(false)
  const [showCropModal, setShowCropModal] = useState(false)
  const [imageSrc, setImageSrc] = useState<string | null>(null)
  const [crop, setCrop] = useState<CropType>()
  const [originalFile, setOriginalFile] = useState<File | null>(null)
  const imgRef = useRef<HTMLImageElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const toast = useToast()

  const maxSize = type === 'company' ? '800x800' : '400x400'
  const maxFileSizeMB = 2
  const title = type === 'company' ? 'Company Logo' : 'Profile Logo'
  const aspect = type === 'profile' ? 1 : undefined

  useEffect(() => {
    if (currentLogoUrl && preview) {
      setPreview(null)
    }
  }, [currentLogoUrl, preview])

  const validateImage = (file: File): Promise<boolean> => {
    return new Promise((resolve) => {
      if (file.size > maxFileSizeMB * 1024 * 1024) {
        toast.error('File too large', `Maximum file size is ${maxFileSizeMB}MB`)
        resolve(false)
        return
      }

      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml']
      if (!validTypes.includes(file.type)) {
        toast.error('Invalid format', 'Only JPEG, PNG, and SVG images are allowed')
        resolve(false)
        return
      }

      resolve(true)
    })
  }

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    const isValid = await validateImage(file)
    if (!isValid) {
      e.target.value = ''
      return
    }

    // SVGs don't need cropping
    if (file.type === 'image/svg+xml') {
      const reader = new FileReader()
      reader.onloadend = () => setPreview(reader.result as string)
      reader.readAsDataURL(file)
      await handleUpload(file)
      e.target.value = ''
      return
    }

    // For raster images, open crop modal
    setOriginalFile(file)
    const reader = new FileReader()
    reader.onloadend = () => {
      setImageSrc(reader.result as string)
      setShowCropModal(true)
    }
    reader.readAsDataURL(file)
    e.target.value = ''
  }

  const onImageLoad = useCallback((e: React.SyntheticEvent<HTMLImageElement>) => {
    imgRef.current = e.currentTarget
    const { width, height } = e.currentTarget
    const newCrop = aspect
      ? centerAspectCrop(width, height, aspect)
      : centerCrop(
          makeAspectCrop({ unit: '%', width: 90 }, width / height, width, height),
          width,
          height
        )
    setCrop(newCrop)
  }, [aspect])

  const getCroppedBlob = useCallback((): Promise<Blob | null> => {
    return new Promise((resolve) => {
      const image = imgRef.current
      if (!image || !crop) {
        resolve(null)
        return
      }

      const canvas = document.createElement('canvas')
      const scaleX = image.naturalWidth / image.width
      const scaleY = image.naturalHeight / image.height

      const pixelCrop = {
        x: (crop.unit === '%' ? (crop.x / 100) * image.width : crop.x) * scaleX,
        y: (crop.unit === '%' ? (crop.y / 100) * image.height : crop.y) * scaleY,
        width: (crop.unit === '%' ? (crop.width / 100) * image.width : crop.width) * scaleX,
        height: (crop.unit === '%' ? (crop.height / 100) * image.height : crop.height) * scaleY,
      }

      canvas.width = pixelCrop.width
      canvas.height = pixelCrop.height

      const ctx = canvas.getContext('2d')
      if (!ctx) {
        resolve(null)
        return
      }

      ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        pixelCrop.width,
        pixelCrop.height
      )

      canvas.toBlob((blob) => resolve(blob), 'image/png', 1)
    })
  }, [crop])

  const handleCropConfirm = async () => {
    const blob = await getCroppedBlob()
    if (!blob || !originalFile) {
      toast.error('Crop Failed', 'Could not crop image')
      return
    }

    const croppedFile = new File([blob], originalFile.name.replace(/\.[^.]+$/, '.png'), {
      type: 'image/png',
    })

    // Show preview
    const previewUrl = URL.createObjectURL(blob)
    setPreview(previewUrl)
    setShowCropModal(false)
    setImageSrc(null)
    setOriginalFile(null)

    await handleUpload(croppedFile)
  }

  const handleCropCancel = () => {
    setShowCropModal(false)
    setImageSrc(null)
    setOriginalFile(null)
  }

  const handleSkipCrop = async () => {
    if (!originalFile) return
    const reader = new FileReader()
    reader.onloadend = () => setPreview(reader.result as string)
    reader.readAsDataURL(originalFile)
    setShowCropModal(false)
    setImageSrc(null)
    await handleUpload(originalFile)
    setOriginalFile(null)
  }

  const handleUpload = async (file: File) => {
    try {
      setUploading(true)

      const formData = new FormData()
      formData.append(fileKey, file)
      formData.append('type', type)

      const endpoint = uploadUrl || '/company/logo/upload';
      const response = await apiClient.post<unknown>(endpoint, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      const data = response.data as { status?: string; message?: string; data?: { logo_url?: string; profile_picture_url?: string } }
      if (data.status === 'success') {
        toast.success('Upload Successful', data.message || 'Logo uploaded successfully')
        
        const url = data.data?.profile_picture_url || data.data?.logo_url;
        if (url) {
          onUploadSuccess(url)
        }
      } else {
        toast.error('Upload Failed', data.message || 'Failed to upload logo')
      }
    } catch (error: unknown) {
      logger.error('Upload error:', error)
      const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } }; message?: string }
      const errorMessage = err.response?.data?.message
        || err.response?.data?.errors?.[fileKey]?.[0]
        || err.message
        || 'Failed to upload logo'
      toast.error('Upload Failed', errorMessage)
    } finally {
      setUploading(false)
    }
  }

  const handleDeleteClick = () => {
      setShowDeleteModal(true)
  }

  const handleConfirmDelete = async () => {
    try {
      setDeleting(true)

      const endpoint = deleteUrl || '/company/logo/delete';
      const response = await apiClient.delete<unknown>(endpoint, {
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
      logger.error('Delete error:', error)
      const err = error as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = err.response?.data?.message || err.message || 'Failed to delete logo'
      toast.error('Delete Failed', errorMessage)
    } finally {
      setDeleting(false)
      setShowDeleteModal(false)
    }
  }

  const displayImageUrl = preview || currentLogoUrl

  return (
    <>
      <div className="space-y-3">
        <label className="block text-sm font-medium text-foreground">{title}</label>

        <div className="flex items-start gap-4">
          <div className="flex-shrink-0 relative group">
            <div className="w-32 h-32 border-2 border-dashed border-border rounded-lg flex items-center justify-center bg-muted/20 overflow-hidden relative">
              {displayImageUrl ? (
                <>
                    <img
                      src={displayImageUrl}
                      alt={title}
                      className="w-full h-full object-cover"
                    />
                    <div 
                      className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
                      onClick={handleDeleteClick}
                      title="Remove Image"
                    >
                        {deleting ? (
                            <span className="text-white text-xs">Deleting...</span>
                        ) : (
                            <Trash2 className="h-8 w-8 text-white" />
                        )}
                    </div>
                </>
              ) : (
                <ImageIcon className="h-12 w-12 text-muted-foreground" />
              )}
            </div>
          </div>

          <div className="flex-1 space-y-2">
            <input
              ref={fileInputRef}
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/svg+xml"
              onChange={handleFileSelect}
              className="hidden"
            />

            <div className="flex flex-col gap-2 items-start">
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
            </div>

            <p className="text-xs text-muted-foreground">
              Max size: {maxSize} pixels, {maxFileSizeMB}MB<br />
              Formats: JPEG, PNG, SVG
            </p>
          </div>
        </div>
      </div>

      {/* Crop Modal */}
      <Dialog open={showCropModal} onOpenChange={(open) => { if (!open) handleCropCancel() }}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Crop className="h-5 w-5" />
              Crop Image
            </DialogTitle>
          </DialogHeader>
          <div className="flex flex-col items-center gap-4">
            {imageSrc && (
              <div className="max-h-[400px] overflow-auto rounded-lg border">
                <ReactCrop
                  crop={crop}
                  onChange={(c) => setCrop(c)}
                  aspect={aspect}
                  className="max-w-full"
                >
                  <img
                    src={imageSrc}
                    onLoad={onImageLoad}
                    alt="Crop preview"
                    className="max-w-full"
                    style={{ maxHeight: '380px' }}
                  />
                </ReactCrop>
              </div>
            )}
            <p className="text-xs text-muted-foreground text-center">
              Drag to adjust the crop area{type === 'profile' ? ' (1:1 ratio)' : ''}. Click "Apply Crop" to upload.
            </p>
          </div>
          <DialogFooter className="flex gap-2 sm:gap-0">
            <Button variant="ghost" size="sm" onClick={handleCropCancel}>
              <X className="h-4 w-4 mr-1" />
              Cancel
            </Button>
            <Button variant="outline" size="sm" onClick={handleSkipCrop}>
              Skip Crop
            </Button>
            <Button size="sm" onClick={handleCropConfirm}>
              <Check className="h-4 w-4 mr-1" />
              Apply Crop
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmationModal 
        isOpen={showDeleteModal}
        onClose={() => setShowDeleteModal(false)}
        onConfirm={handleConfirmDelete}
        title="Delete Photo"
        description={`Are you sure you want to delete this ${title.toLowerCase()}? This action cannot be undone.`}
        confirmText="Delete"
        cancelText="Cancel"
        type="danger"
        isLoading={deleting}
      />
    </>
  )
}
