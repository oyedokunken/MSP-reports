import { useState, useRef, useCallback, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { logger } from '@/lib/logger'
import { Upload, Trash2, Image as ImageIcon, Crop, Check, X, ZoomIn, ZoomOut, RotateCw } from 'lucide-react'
import { useToast } from '@/components/ui/Toast'
import apiClient from '@/lib/api'
import { ConfirmationModal } from '@/components/ui/ConfirmationModal'
import Cropper from 'react-easy-crop'
import type { Area, Point } from 'react-easy-crop'
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

async function getCroppedImg(imageSrc: string, pixelCrop: Area, rotation = 0): Promise<Blob | null> {
  const image = await createImage(imageSrc)
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  const radians = (rotation * Math.PI) / 180

  // Calculate bounding box of the rotated image
  const sin = Math.abs(Math.sin(radians))
  const cos = Math.abs(Math.cos(radians))
  const bBoxWidth = image.width * cos + image.height * sin
  const bBoxHeight = image.width * sin + image.height * cos

  canvas.width = bBoxWidth
  canvas.height = bBoxHeight

  ctx.translate(bBoxWidth / 2, bBoxHeight / 2)
  ctx.rotate(radians)
  ctx.translate(-image.width / 2, -image.height / 2)
  ctx.drawImage(image, 0, 0)

  // Extract the cropped area
  const data = ctx.getImageData(pixelCrop.x, pixelCrop.y, pixelCrop.width, pixelCrop.height)

  canvas.width = pixelCrop.width
  canvas.height = pixelCrop.height
  ctx.putImageData(data, 0, 0)

  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), 'image/png', 1)
  })
}

function createImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.addEventListener('load', () => resolve(image))
    image.addEventListener('error', (error) => reject(error))
    image.setAttribute('crossOrigin', 'anonymous')
    image.src = url
  })
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
  const [originalFile, setOriginalFile] = useState<File | null>(null)
  const [cropPos, setCropPos] = useState<Point>({ x: 0, y: 0 })
  const [zoom, setZoom] = useState(1)
  const [rotation, setRotation] = useState(0)
  const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const toast = useToast()

  const maxFileSizeMB = 5
  const title = type === 'company' ? 'Company Logo' : 'Profile Logo'
  const aspect = type === 'profile' ? 1 : 4 / 3

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

      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp']
      if (!validTypes.includes(file.type)) {
        toast.error('Invalid format', 'Only JPEG, PNG, WebP, and SVG images are allowed')
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
      setCropPos({ x: 0, y: 0 })
      setZoom(1)
      setRotation(0)
      setShowCropModal(true)
    }
    reader.readAsDataURL(file)
    e.target.value = ''
  }

  const onCropComplete = useCallback((_croppedArea: Area, croppedAreaPx: Area) => {
    setCroppedAreaPixels(croppedAreaPx)
  }, [])

  const handleCropConfirm = async () => {
    if (!imageSrc || !croppedAreaPixels || !originalFile) {
      toast.error('Crop Failed', 'Could not crop image')
      return
    }

    try {
      const blob = await getCroppedImg(imageSrc, croppedAreaPixels, rotation)
      if (!blob) {
        toast.error('Crop Failed', 'Could not crop image')
        return
      }

      const croppedFile = new File([blob], originalFile.name.replace(/\.[^.]+$/, '.png'), {
        type: 'image/png',
      })

      const previewUrl = URL.createObjectURL(blob)
      setPreview(previewUrl)
      setShowCropModal(false)
      setImageSrc(null)
      setOriginalFile(null)

      await handleUpload(croppedFile)
    } catch {
      toast.error('Crop Failed', 'An error occurred while cropping')
    }
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
              accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp"
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
              Max size: {maxFileSizeMB}MB &middot; JPEG, PNG, WebP, SVG
            </p>
          </div>
        </div>
      </div>

      {/* Crop & Zoom Modal */}
      <Dialog open={showCropModal} onOpenChange={(open) => { if (!open) handleCropCancel() }}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Crop className="h-5 w-5" />
              Crop & Zoom Image
            </DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-4">
            {imageSrc && (
              <div className="relative w-full rounded-lg overflow-hidden bg-black/90" style={{ height: '350px' }}>
                <Cropper
                  image={imageSrc}
                  crop={cropPos}
                  zoom={zoom}
                  rotation={rotation}
                  aspect={aspect}
                  cropShape={type === 'profile' ? 'round' : 'rect'}
                  showGrid={true}
                  onCropChange={setCropPos}
                  onZoomChange={setZoom}
                  onRotationChange={setRotation}
                  onCropComplete={onCropComplete}
                  minZoom={0.5}
                  maxZoom={5}
                  zoomSpeed={0.2}
                />
              </div>
            )}

            {/* Zoom & Rotation Controls */}
            <div className="space-y-3">
              <div className="flex items-center gap-3">
                <ZoomOut className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <input
                  type="range"
                  min={0.5}
                  max={5}
                  step={0.1}
                  value={zoom}
                  onChange={(e) => setZoom(Number(e.target.value))}
                  className="flex-1 h-1.5 bg-muted rounded-full appearance-none cursor-pointer accent-primary"
                />
                <ZoomIn className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <span className="text-xs text-muted-foreground w-10 text-right">{zoom.toFixed(1)}x</span>
              </div>
              <div className="flex items-center gap-3">
                <RotateCw className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                <input
                  type="range"
                  min={0}
                  max={360}
                  step={1}
                  value={rotation}
                  onChange={(e) => setRotation(Number(e.target.value))}
                  className="flex-1 h-1.5 bg-muted rounded-full appearance-none cursor-pointer accent-primary"
                />
                <span className="text-xs text-muted-foreground w-10 text-right">{rotation}&deg;</span>
              </div>
            </div>

            <p className="text-xs text-muted-foreground text-center">
              Scroll to zoom &middot; Drag to pan &middot; {type === 'profile' ? 'Circle crop (1:1)' : 'Rectangle crop'}
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
              Apply & Upload
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
