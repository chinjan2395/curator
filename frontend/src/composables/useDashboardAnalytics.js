import { computed, ref } from 'vue'
import { useWorkspacesStore } from '../stores/workspaces'
import { useAsync } from './useAsync'
import axios from 'axios'

const MS_PER_DAY = 1000 * 60 * 60 * 24
const MONTH_WINDOW = 6

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value))
}

function daysSince(value) {
  if (!value) return null
  const parsed = new Date(value).getTime()
  if (Number.isNaN(parsed)) return null
  return Math.floor((Date.now() - parsed) / MS_PER_DAY)
}

function monthKey(value) {
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null
  return `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}`
}

function createMonthBuckets(windowSize = MONTH_WINDOW) {
  const currentMonth = new Date()
  currentMonth.setDate(1)
  currentMonth.setHours(0, 0, 0, 0)

  const buckets = []
  for (let offset = windowSize - 1; offset >= 0; offset -= 1) {
    const bucketDate = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - offset, 1)
    buckets.push({
      key: `${bucketDate.getFullYear()}-${String(bucketDate.getMonth() + 1).padStart(2, '0')}`,
      label: bucketDate.toLocaleString('en-US', { month: 'short' }),
      feeds: 0,
      workspaces: 0,
    })
  }
  return buckets
}

export function useDashboardAnalytics() {
  const workspaces = useWorkspacesStore()
  const allFeeds = ref([])
  const feedCountsByWorkspace = ref({})
  const userSyncSummary = ref({
    new_post_count: 0,
    scheduler_synced_post_count: 0,
    scheduler_unread_count: 0,
    broken_credentials: [],
  })
  const socialOverview = ref(null)

  const { loading: analyticsLoading, execute: loadAnalytics } = useAsync(async () => {
    const [, summaryResponse, analyticsResponse] = await Promise.all([
      workspaces.fetchAll(),
      axios.get('/api/user/sync-summary').catch(() => ({
        data: {
          new_post_count: 0,
          scheduler_synced_post_count: 0,
          scheduler_unread_count: 0,
          broken_credentials: [],
        },
      })),
      axios.get('/api/analytics/overview').catch(() => ({ data: { data: null } })),
    ])
    socialOverview.value = analyticsResponse?.data?.data || analyticsResponse?.data || null
    userSyncSummary.value = summaryResponse?.data || {
      new_post_count: 0,
      scheduler_synced_post_count: 0,
      scheduler_unread_count: 0,
      broken_credentials: [],
    }

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
      all.push(...r.feeds.map((feed) => ({ ...feed, workspace_id: r.id })))
    }
    feedCountsByWorkspace.value = counts
    allFeeds.value = all
  })

  const totalWorkspaces = computed(() => workspaces.list.length)
  const totalFeeds = computed(() => allFeeds.value.length)
  const avgFeedsPerWorkspace = computed(() =>
    totalWorkspaces.value ? Number((totalFeeds.value / totalWorkspaces.value).toFixed(1)) : 0,
  )
  const publishedWorkspaces = computed(() =>
    workspaces.list.filter((w) => String(w.public_key || '').trim()).length,
  )
  const syncedFeeds = computed(() => allFeeds.value.filter((f) => f.last_synced_at).length)
  const recentWorkspaceAdds = computed(() =>
    workspaces.list.filter((workspace) => {
      const age = daysSince(workspace.created_at)
      return age !== null && age <= 30
    }).length,
  )
  const recentFeedAdds = computed(() =>
    allFeeds.value.filter((feed) => {
      const age = daysSince(feed.created_at)
      return age !== null && age <= 30
    }).length,
  )
  const syncBuckets = computed(() => {
    const counts = { active: 0, aging: 0, stale: 0, never: 0 }

    allFeeds.value.forEach((feed) => {
      const age = daysSince(feed.last_synced_at)
      if (age === null) {
        counts.never += 1
      } else if (age <= 7) {
        counts.active += 1
      } else if (age <= 30) {
        counts.aging += 1
      } else {
        counts.stale += 1
      }
    })

    return counts
  })
  const activeFeeds = computed(() => syncBuckets.value.active)
  const agingFeeds = computed(() => syncBuckets.value.aging)
  const staleFeeds = computed(() => syncBuckets.value.stale)
  const neverSyncedFeeds = computed(() => syncBuckets.value.never)
  const maxFeedCount = computed(() =>
    Math.max(1, ...Object.values(feedCountsByWorkspace.value).map((v) => Number(v || 0))),
  )
  const workspaceUtilization = computed(() => {
    if (!totalWorkspaces.value) return 0
    return clamp(Math.round((avgFeedsPerWorkspace.value / 4) * 100), 12, 100)
  })
  const publishedCoverage = computed(() =>
    totalWorkspaces.value ? Math.round((publishedWorkspaces.value / totalWorkspaces.value) * 100) : 0,
  )
  const syncCoverage = computed(() =>
    totalFeeds.value ? Math.round((syncedFeeds.value / totalFeeds.value) * 100) : 0,
  )
  const healthySyncRate = computed(() =>
    totalFeeds.value ? Math.round((activeFeeds.value / totalFeeds.value) * 100) : 0,
  )
  const distinctFeedTypes = computed(() =>
    new Set(
      allFeeds.value
        .map((feed) => String(feed.type || '').trim())
        .filter(Boolean),
    ),
  )
  const sourceDiversity = computed(() => distinctFeedTypes.value.size)

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
        share: totalFeeds.value ? Math.round((Number(count || 0) / totalFeeds.value) * 100) : 0,
        width: Math.max(10, Math.round((Number(count || 0) / maxTypeCount.value) * 100)),
      }))
      .sort((a, b) => b.count - a.count),
  )
  const dominantFeedType = computed(() => feedTypeDistribution.value[0] || null)
  const brokenCredentials = computed(() => userSyncSummary.value?.broken_credentials || [])
  const brokenCredentialCount = computed(() => brokenCredentials.value.length)
  const newPostCount = computed(() => Number(userSyncSummary.value?.scheduler_synced_post_count || 0))
  const attentionCount = computed(() =>
    brokenCredentialCount.value + staleFeeds.value + neverSyncedFeeds.value,
  )
  const unpublishedWorkspaces = computed(() =>
    Math.max(0, totalWorkspaces.value - publishedWorkspaces.value),
  )
  const syncStatusBreakdown = computed(() => {
    const total = totalFeeds.value || 1
    return [
      {
        key: 'active',
        label: 'Healthy',
        count: activeFeeds.value,
        description: 'Synced in the last 7 days',
        tone: 'emerald',
        width: Math.round((activeFeeds.value / total) * 100),
      },
      {
        key: 'aging',
        label: 'Aging',
        count: agingFeeds.value,
        description: 'Synced 8 to 30 days ago',
        tone: 'amber',
        width: Math.round((agingFeeds.value / total) * 100),
      },
      {
        key: 'stale',
        label: 'Stale',
        count: staleFeeds.value,
        description: 'No sync in over 30 days',
        tone: 'rose',
        width: Math.round((staleFeeds.value / total) * 100),
      },
      {
        key: 'never',
        label: 'Never synced',
        count: neverSyncedFeeds.value,
        description: 'Configured but never pulled content',
        tone: 'slate',
        width: Math.round((neverSyncedFeeds.value / total) * 100),
      },
    ]
  })
  const growthTimeline = computed(() => {
    const buckets = createMonthBuckets()
    const bucketMap = Object.fromEntries(buckets.map((bucket) => [bucket.key, bucket]))

    workspaces.list.forEach((workspace) => {
      const key = monthKey(workspace.created_at)
      if (key && bucketMap[key]) bucketMap[key].workspaces += 1
    })

    allFeeds.value.forEach((feed) => {
      const key = monthKey(feed.created_at)
      if (key && bucketMap[key]) bucketMap[key].feeds += 1
    })

    return buckets
  })
  const overallHealthScore = computed(() => {
    if (!totalWorkspaces.value && !totalFeeds.value) return 0
    const diversityScore = clamp(Math.round((sourceDiversity.value / 5) * 100), 0, 100)
    return Math.round(
      (healthySyncRate.value * 0.45)
      + (publishedCoverage.value * 0.35)
      + (diversityScore * 0.2),
    )
  })
  const topWorkspaces = computed(() =>
    workspaces.list
      .map((workspace) => {
        const workspaceFeeds = allFeeds.value.filter((feed) => feed.workspace_id === workspace.id)
        const count = workspaceFeeds.length
        const activeCount = workspaceFeeds.filter((feed) => {
          const age = daysSince(feed.last_synced_at)
          return age !== null && age <= 7
        }).length
        const staleCount = workspaceFeeds.filter((feed) => {
          const age = daysSince(feed.last_synced_at)
          return age === null || age > 30
        }).length
        const sourceCount = new Set(
          workspaceFeeds.map((feed) => String(feed.type || '').trim()).filter(Boolean),
        ).size
        const published = Boolean(String(workspace.public_key || '').trim())
        const syncRatio = count ? activeCount / count : 0
        const score = Math.round(
          (syncRatio * 65)
          + (published ? 20 : 0)
          + Math.min(15, sourceCount * 5),
        )

        return {
          id: workspace.id,
          name: workspace.name,
          count,
          activeCount,
          staleCount,
          published,
          score,
          scoreWidth: Math.max(12, score),
          width: Math.max(10, Math.round((count / maxFeedCount.value) * 100)),
          status: score >= 80 ? 'Strong' : (score >= 55 ? 'Stable' : 'Needs attention'),
        }
      })
      .sort((a, b) => (b.score - a.score) || (b.count - a.count) || a.name.localeCompare(b.name))
      .slice(0, 6),
  )
  const headlineStats = computed(() => {
    const stats = [
      {
        label: 'Dominant source',
        value: dominantFeedType.value ? `${dominantFeedType.value.share}% ${dominantFeedType.value.type}` : 'No feeds yet',
      },
      {
        label: 'Healthy sync rate',
        value: totalFeeds.value ? `${healthySyncRate.value}% of feeds` : 'No sync history',
      },
      {
        label: 'Scheduler synced',
        value: `${newPostCount.value} posts since login`,
      },
    ]
    if (socialOverview.value) {
      stats.push({
        label: 'Social engagement',
        value: `${socialOverview.value.engagement_rate || 0}% · ${socialOverview.value.total_likes || 0} likes`,
      })
    }
    return stats
  })
  const attentionItems = computed(() => {
    const items = []

    if (brokenCredentialCount.value) {
      items.push({
        title: `${brokenCredentialCount.value} credential${brokenCredentialCount.value === 1 ? '' : 's'} need reconnection`,
        detail: 'Broken provider links will block future syncs until they are reconnected.',
      })
    }

    if (neverSyncedFeeds.value) {
      items.push({
        title: `${neverSyncedFeeds.value} feed${neverSyncedFeeds.value === 1 ? '' : 's'} have never synced`,
        detail: 'Run an initial sync so these sources start contributing content.',
      })
    }

    if (unpublishedWorkspaces.value) {
      items.push({
        title: `${unpublishedWorkspaces.value} workspace${unpublishedWorkspaces.value === 1 ? '' : 's'} are not published`,
        detail: 'Publishing them will make curated output available to downstream embeds and APIs.',
      })
    }

    if (!items.length) {
      items.push({
        title: 'No immediate blockers detected',
        detail: 'Sync, publishing, and account health all look stable from the available signals.',
      })
    }

    return items
  })

  return {
    analyticsLoading,
    loadAnalytics,
    workspaces,
    totalWorkspaces,
    totalFeeds,
    avgFeedsPerWorkspace,
    publishedWorkspaces,
    syncedFeeds,
    activeFeeds,
    agingFeeds,
    staleFeeds,
    neverSyncedFeeds,
    recentWorkspaceAdds,
    recentFeedAdds,
    workspaceUtilization,
    publishedCoverage,
    syncCoverage,
    healthySyncRate,
    sourceDiversity,
    dominantFeedType,
    newPostCount,
    brokenCredentials,
    brokenCredentialCount,
    attentionCount,
    unpublishedWorkspaces,
    syncStatusBreakdown,
    growthTimeline,
    overallHealthScore,
    headlineStats,
    attentionItems,
    topWorkspaces,
    feedTypeDistribution,
    socialOverview,
  }
}
