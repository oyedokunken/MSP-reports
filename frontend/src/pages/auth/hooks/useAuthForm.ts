import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/contexts/AuthContext'

interface UseAuthFormOptions {
  redirectTo?: string
  onSuccess?: () => void
  onError?: (error: Error) => void
}

export function useAuthForm(options: UseAuthFormOptions = {}) {
  const { redirectTo = '/dashboard', onSuccess, onError } = options
  
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  
  const navigate = useNavigate()
  const { login } = useAuth()

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    
    // Validation
    if (!email || !password) {
      setError('Email and password are required')
      return
    }

    setError('')
    setIsSubmitting(true)

    try {
      await login({ 
        email, 
        password,
        auth_method: 'local' // Default to local auth
      })
      
      // Success callback
      onSuccess?.()
      
      // Navigate to dashboard or specified route
      navigate(redirectTo)
    } catch (err: any) {
      // Handle different error scenarios
      let errorMessage = 'Failed to sign in. Please try again.'
      
      if (err.message === 'Invalid credentials') {
        errorMessage = 'Invalid email or password. Please check your credentials and try again.'
      } else if (err.response?.data?.message) {
        errorMessage = err.response.data.message
      } else if (err.message) {
        errorMessage = err.message
      }
      
      setError(errorMessage)
      
      // Error callback
      onError?.(err)
    } finally {
      setIsSubmitting(false)
    }
  }

  const resetForm = () => {
    setEmail('')
    setPassword('')
    setError('')
    setIsSubmitting(false)
  }

  return {
    // Form state
    email,
    password,
    error,
    isSubmitting,
    
    // Form handlers
    setEmail,
    setPassword,
    setError,
    handleSubmit,
    resetForm,
  }
}
