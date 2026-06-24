import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [] as { id: number; message: string; read: boolean }[]
  }),
  getters: {
    unreadCount: (state) => state.notifications.filter((n) => !n.read).length
  },
  actions: {
    add(message: string) {
      this.notifications.push({ id: Date.now(), message, read: false })
    },
    markAllRead() {
      this.notifications.forEach((n) => (n.read = true))
    }
  }
})
