export interface GeneralSettings {
  surefeedback_role_can_comment: string[]
  surefeedback_guest_comments_enabled: boolean
  surefeedback_admin: boolean
}

export interface ConnectionSettings {
  surefeedback_id: number | ''
  surefeedback_api_key: string
  surefeedback_access_token: string
  surefeedback_parent_url: string
  surefeedback_signature: string
  surefeedback_installed: boolean
}

export interface WhiteLabelSettings {
  surefeedback_plugin_name: string
  surefeedback_plugin_description: string
  surefeedback_plugin_author: string
  surefeedback_plugin_author_url: string
  surefeedback_plugin_link: string
}

export interface ConnectionStatus {
  connected: boolean
  parent_url?: string
  project_id?: number
  last_verified?: string
}

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  message?: string
  errors?: Record<string, string>
}

export interface TabConfig {
  id: string
  label: string
  visible: boolean
}

export interface SettingsState {
  general: GeneralSettings
  connection: ConnectionSettings
  whiteLabel: WhiteLabelSettings
  connectionStatus: ConnectionStatus
  loading: boolean
  saving: boolean
  errors: Record<string, string>
  activeTab: string
}

export interface UserRole {
  name: string
  label: string
  selected: boolean
}

export interface ManualImportData {
  project_id: number
  api_key: string
  access_token: string
  parent_url: string
  signature: string
}