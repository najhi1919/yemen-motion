export interface User {
  id: number
  name: string
  email: string
  avatar?: string | null
  created_at: string
}

export interface AuthData {
  user: User
  token: string
  role: string
  permissions: string[]
}

export interface ApiResponse<T = unknown> {
  success: boolean
  data: T | null
  message: string
  errors: Record<string, string[]> | null
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  role: 'client' | 'designer' | 'admin' | 'staff'
}

export interface ResetPasswordPayload {
  email: string
  token: string
  password: string
  password_confirmation: string
}
