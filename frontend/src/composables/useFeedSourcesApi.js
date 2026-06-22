import axios from 'axios'
import { hydrateFromSession, isFresh, persistToSession, withDedupe } from '../utils/sessionCache'

const FEED_SOURCE_TTL_MS = 15 * 60 * 1000

function sourceCacheKey(workspaceId, provider, socialCredentialId) {
  return `feed-source:${workspaceId}:${provider}:${socialCredentialId}`
}

async function cachedGet(workspaceId, provider, socialCredentialId, url, { force = false } = {}) {
  const cacheKey = sourceCacheKey(workspaceId, provider, socialCredentialId)
  const cached = hydrateFromSession(cacheKey)

  if (!force && cached && isFresh(cached, FEED_SOURCE_TTL_MS)) {
    return cached.value
  }

  return withDedupe(cacheKey, async () => {
    const { data } = await axios.get(url, {
      params: { social_credential_id: socialCredentialId },
    })
    persistToSession(cacheKey, data)
    return data
  })
}

export async function fetchInstagramAccounts(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'instagram',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/instagram/accounts`,
    options,
  )
}

export async function fetchThreadsAccount(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'threads',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/threads/account`,
    options,
  )
}

export async function fetchTikTokAccount(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'tiktok',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/tiktok/account`,
    options,
  )
}

export async function fetchTwitterAccount(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'twitter',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/twitter/account`,
    options,
  )
}

export async function fetchYoutubeChannels(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'youtube',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/youtube/channels`,
    options,
  )
}

export async function fetchFacebookPages(workspaceId, socialCredentialId, options = {}) {
  return cachedGet(
    workspaceId,
    'facebook',
    socialCredentialId,
    `/api/workspaces/${workspaceId}/feeds/facebook/pages`,
    options,
  )
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
