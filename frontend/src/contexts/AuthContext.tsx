import { createContext, useContext, useState, useEffect, type ReactNode } from 'react'
import type { User, LoginCredentials, LoginResponse, AuthState } from '@/types/auth'
import { api } from '@/lib/api'
import { mockLogin, MOCK_USER } from '@/lib/mockData'

// Use real API authentication (set to false to use real backend)
const USE_MOCK_AUTH = false

interface AuthContextType extends AuthState {
  login: (credentials: LoginCredentials) => Promise<void>
  logout: () => Promise<void>
  refreshUser: () => Promise<void>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: true,
  })

  // Initialize auth state from localStorage
  useEffect(() => {
    const initializeAuth = async () => {
      const token = localStorage.getItem('auth_token')
      const userStr = localStorage.getItem('user')

      if (token && userStr) {
        try {
          const user: User = JSON.parse(userStr)
          setState({
            user,
            token,
            isAuthenticated: true,
            isLoading: false,
          })

          // Optionally verify token with backend
          await refreshUser()
        } catch (error) {
          console.error('Failed to parse stored user:', error)
          logout()
        }
      } else {
        setState((prev) => ({ ...prev, isLoading: false }))
      }
    }

    initializeAuth()
  }, [])

  const login = async (credentials: LoginCredentials) => {
    try {
      let user: User
      let token: string

      if (USE_MOCK_AUTH) {
        // Use mock authentication in development
        const mockResponse = await mockLogin(credentials.email, credentials.password)
        user = mockResponse.user
        token = mockResponse.token
      } else {
        // Use real API in production
        const response = await api.post<LoginResponse>('/auth/login', credentials)

        if (!response.data) {
          throw new Error('Login failed')
        }

        user = response.data.user
        token = response.data.token
      }

      // Store in localStorage
      localStorage.setItem('auth_token', token)
      localStorage.setItem('user', JSON.stringify(user))

      setState({
        user,
        token,
        isAuthenticated: true,
        isLoading: false,
      })
    } catch (error) {
      throw error
    }
  }

  const logout = async () => {
    try {
      // Call logout endpoint
      await api.post('/auth/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      // Clear local state regardless of API call success
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')

      setState({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
      })
    }
  }

  const refreshUser = async () => {
    try {
      if (USE_MOCK_AUTH) {
        // Use mock user in development
        const user = MOCK_USER
        localStorage.setItem('user', JSON.stringify(user))

        setState((prev) => ({
          ...prev,
          user,
          isAuthenticated: true,
        }))
      } else {
        // Use real API in production
        const response = await api.get<User>('/auth/me')

        if (response.success && response.data) {
          const user = response.data
          localStorage.setItem('user', JSON.stringify(user))

          setState((prev) => ({
            ...prev,
            user,
            isAuthenticated: true,
          }))
        }
      }
    } catch (error) {
      console.error('Failed to refresh user:', error)
      if (!USE_MOCK_AUTH) {
        logout()
      }
    }
  }

  return (
    <AuthContext.Provider
      value={{
        ...state,
        login,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
