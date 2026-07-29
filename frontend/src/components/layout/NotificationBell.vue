<template>
  <AppDropdown v-if="showNotifications" align="right">
    <template #trigger="{ open }">
      <button
        type="button"
        class="relative p-2 rounded-lg transition-colors"
        :class="syncUnreadCount > 0 ? 'text-blue-600 bg-blue-50 hover:bg-blue-100' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'"
        title="Notifications"
        @click="!open && onOpen()"
      >
        <AppIcon name="bell" class="w-5 h-5" />
        <span
          v-if="headerUnreadCount > 0"
          class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-blue-600 text-white text-[10px] leading-[1.1rem] font-semibold text-center"
        >
          {{ headerUnreadCount > 99 ? '99+' : headerUnreadCount }}
        </span>
      </button>
    </template>

    <template #default="{ close }">
      <div class="w-96 max-w-[calc(100vw-2rem)] max-h-[32rem] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 shrink-0">
          <span class="text-sm font-semibold text-slate-800">Notifications</span>
          <button
            v-if="notifications.unreadCount > 0"
            type="button"
            class="text-xs font-medium text-blue-600 hover:text-blue-700 transition-colors"
            @click="notifications.markAllRead()"
          >
            Mark all read
          </button>
        </div>

        <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
          <div v-if="!notifications.items.length" class="flex items-center justify-center px-4 py-10 text-sm text-slate-400">
            You're all caught up
          </div>
          <button
            v-for="n in notifications.items.slice(0, 8)"
            :key="n.id"
            type="button"
            class="w-full flex items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
            :class="n.read_at ? 'bg-white' : 'bg-slate-50/70'"
            @click="!n.read_at && notifications.markRead(n.id)"
          >
            <span class="mt-1.5 h-1.5 w-1.5 rounded-full shrink-0" :class="!n.read_at ? 'bg-blue-600' : 'bg-transparent'" />
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset"
              :class="getNotificationMeta(n).iconWrapClass"
            >
              <AppIcon :name="getNotificationMeta(n).icon" class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1 space-y-0.5">
              <span class="block text-sm font-medium text-slate-800 truncate">{{ n.title }}</span>
              <span class="block text-xs text-slate-500 line-clamp-2">{{ n.body }}</span>
              <span class="block text-[11px] text-slate-400">{{ formatRelativeTime(n.created_at) }}</span>
            </span>
          </button>
        </div>

        <div class="shrink-0 border-t border-slate-100 px-4 py-2.5">
          <button
            type="button"
            class="w-full text-center text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors"
            @click="router.push('/notifications'); close();"
          >
            View all notifications
          </button>
        </div>
      </div>
    </template>
  </AppDropdown>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationsStore } from '../../stores/notifications';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import { useNavigationVisibility } from '../../composables/useNavigationVisibility';
import { AppDropdown, AppIcon } from '../ui';
import { getNotificationMeta } from '../../utils/notificationDisplay';
import { formatRelativeTime } from '../../utils/datetime';

const { isMenuEnabled } = useNavigationVisibility();
const showNotifications = computed(() => isMenuEnabled('notifications'));

const notifications = useNotificationsStore();
const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();

const syncUnreadCount = computed(() => Number(auth.syncSummary?.scheduler_unread_count || 0));
const headerUnreadCount = computed(() => {
  const sync = syncUnreadCount.value;
  if (!showNotifications.value) return sync;
  return sync + Number(notifications.unreadCount || 0);
});

async function onOpen() {
  const syncUnread = syncUnreadCount.value;
  if (syncUnread > 0) {
    toast.info(`${syncUnread} new post${syncUnread !== 1 ? 's' : ''} synced by scheduler/job.`);
    await auth.acknowledgeSyncNotifications();
  }
  await notifications.fetchAll();
}
</script>
