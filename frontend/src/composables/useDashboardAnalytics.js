import { computed, ref } from 'vue'
import { useWorkspacesStore } from '../stores/workspaces'
import { useAsync } from './useAsync'
import axios from 'axios'

export function useDashboardAnalytics() {
  const workspaces = useWorkspacesStore()
  const allFeeds = ref([])
  const feedCountsByWorkspace = ref({})

  const { loading: analyticsLoading, execute: loadAnalytics } = useAsync(async () => {
    await workspaces.fetchAll()

    const responses = await Promise.all(
      workspaces.list.map(async (w) => {
        try {
          const { data } = await axios.get(`/api/workspaces/${w.id}/feeds`)
          const feeds = Array.isArray(data) ? data : (data?.data ?? [])
          return { id: w.id, feeds }
        } catch {
          return { id: w.id, feeds: [] }
        }
      }),
    )

    const counts = {}
    const all = []
    for (const r of responses) {
      counts[r.id] = r.feeds.length
      all.push(...r.feeds)
    }
    feedCountsByWorkspace.value = counts
    allFeeds.value = all
  })

  const totalWorkspaces = computed(() => workspaces.list.length)
  const totalFeeds = computed(() => allFeeds.value.length)
  const avgFeedsPerWorkspace = computed(() =>
    totalWorkspaces.value ? Number((totalFeeds.value / totalWorkspaces.value).toFixed(1)) : 0,
  )
  const publishedFeeds = computed(() =>
    workspaces.list.filter((w) => String(w.public_key || '').trim()).length,
  )
  const syncedFeeds = computed(() => allFeeds.value.filter((f) => f.last_synced_at).length)
  const maxFeedCount = computed(() =>
    Math.max(1, ...Object.values(feedCountsByWorkspace.value).map((v) => Number(v || 0))),
  )
  const workspaceUtilization = computed(() => Math.min(100, Math.round((totalWorkspaces.value / 12) * 100)))
  const publishedCoverage = computed(() =>
    totalWorkspaces.value ? Math.round((publishedFeeds.value / totalWorkspaces.value) * 100) : 0,
  )
  const syncCoverage = computed(() =>
    totalFeeds.value ? Math.round((syncedFeeds.value / totalFeeds.value) * 100) : 0,
  )
  const workspaceBars = computed(() =>
    workspaces.list.map((w) => {
      const count = Number(feedCountsByWorkspace.value[w.id] || 0)
      return { id: w.id, name: w.name, count, height: Math.max(14, Math.round((count / maxFeedCount.value) * 100)) }
    }),
  )
  const topWorkspaces = computed(() =>
    workspaces.list
      .map((w) => {
        const count = Number(feedCountsByWorkspace.value[w.id] || 0)
        return { id: w.id, name: w.name, count, width: Math.max(10, Math.round((count / maxFeedCount.value) * 100)) }
      })
      .sort((a, b) => b.count - a.count)
      .slice(0, 6),
  )

  const feedTypeCounts = computed(() =>
    allFeeds.value.reduce((acc, f) => {
      const type = String(f.type || 'other')
      acc[type] = (acc[type] || 0) + 1
      return acc
    }, {}),
  )
  const maxTypeCount = computed(() =>
    Math.max(1, ...Object.values(feedTypeCounts.value).map((v) => Number(v || 0))),
  )
  const feedTypeDistribution = computed(() =>
    Object.entries(feedTypeCounts.value)
      .map(([type, count]) => ({
        type,
        count: Number(count || 0),
        width: Math.max(10, Math.round((Number(count || 0) / maxTypeCount.value) * 100)),
      }))
      .sort((a, b) => b.count - a.count),
  )

  return {
    analyticsLoading,
    loadAnalytics,
    workspaces,
    totalWorkspaces,
    totalFeeds,
    avgFeedsPerWorkspace,
    publishedFeeds,
    syncedFeeds,
    workspaceUtilization,
    publishedCoverage,
    syncCoverage,
    workspaceBars,
    topWorkspaces,
    feedTypeDistribution,
  }
}
