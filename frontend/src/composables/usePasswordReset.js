import axios from 'axios'

export async function requestPasswordResetLink(email) {
  const { data } = await axios.post('/api/forgot-password', { email })
  return data
}

export async function resetPassword(payload) {
  const { data } = await axios.post('/api/reset-password', payload)
  return data
}
