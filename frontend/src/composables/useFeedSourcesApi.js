import axios from 'axios'

export async function fetchInstagramAccounts(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/instagram/accounts`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function fetchThreadsAccount(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/threads/account`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function fetchTikTokAccount(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/tiktok/account`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function fetchTwitterAccount(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/twitter/account`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function fetchYoutubeChannels(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/youtube/channels`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function fetchFacebookPages(workspaceId, socialCredentialId) {
  const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/facebook/pages`, {
    params: { social_credential_id: socialCredentialId },
  })
  return data
}

export async function testYoutube(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-youtube`, payload)
}

export async function testFacebook(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-facebook`, payload)
}

export async function testInstagram(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-instagram`, payload)
}

export async function testTwitter(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-twitter`, payload)
}

export async function testThreads(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-threads`, payload)
}

export async function testTikTok(workspaceId, payload) {
  return axios.post(`/api/workspaces/${workspaceId}/feeds/test-tiktok`, payload)
}

export async function testRssFeed(workspaceId, sourceUrl) {
  const { data } = await axios.post(`/api/workspaces/${workspaceId}/feeds/test-rss`, {
    source_url: sourceUrl,
  })
  return data
}
