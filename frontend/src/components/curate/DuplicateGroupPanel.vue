<template>
  <div class="dup-panel space-y-4">
    <div
      v-for="group in groups"
      :key="group.id"
      class="dup-group rounded-xl border border-amber-200 bg-amber-50/40 p-3 space-y-2.5"
    >
      <!-- Group header -->
      <div class="flex items-center gap-2">
        <span class="text-2xs font-bold uppercase tracking-wider text-amber-700 bg-amber-100 border border-amber-200 px-1.5 py-0.5 rounded-full">
          {{ matchTypeLabel(group.match_type) }}
        </span>
        <span class="text-2xs text-slate-400">{{ group.posts?.length ?? 0 }} posts</span>
      </div>

      <!-- Posts row -->
      <div class="flex gap-2.5 overflow-x-auto pb-1">
        <div
          v-for="post in group.posts"
          :key="post.id"
          class="dup-post-card flex-shrink-0 w-44 rounded-lg border border-slate-200 bg-white overflow-hidden"
        >
          <!-- Thumbnail -->
          <div class="aspect-video bg-slate-100 overflow-hidden">
            <img
              v-if="post.thumbnail_url"
              :src="post.thumbnail_url"
              :alt="post.title || 'Post'"
              class="w-full h-full object-cover"
              @error="(e) => e.target.style.display = 'none'"
            >
            <div v-else class="w-full h-full flex items-center justify-center text-slate-300 text-xs">No preview</div>
          </div>
          <!-- Caption snippet -->
          <div class="p-2 space-y-1">
            <div class="text-2xs text-slate-700 line-clamp-2 leading-relaxed">{{ post.content || post.title || '—' }}</div>
            <div class="text-2xs text-slate-400">{{ getFeedPlatform(post.feed_id) }}</div>
          </div>
          <!-- Keep button -->
          <div class="px-2 pb-2">
            <AppButton
              variant="success"
              size="sm"
              class="w-full !text-2xs"
              :disabled="keepingGroupId === group.id"
              @click="$emit('keep', group.id, post.id)"
            >
              Keep this
            </AppButton>
          </div>
        </div>
      </div>

      <!-- Dismiss -->
      <div class="flex justify-end">
        <AppButton
          variant="ghost"
          size="sm"
          class="!text-2xs text-slate-400 hover:text-slate-600"
          :disabled="keepingGroupId === group.id"
          @click="$emit('dismiss', group.id)"
        >
          Not duplicates — dismiss
        </AppButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { AppButton } from '../ui';

defineOptions({ name: 'DuplicateGroupPanel' });

const props = defineProps({
  groups: { type: Array, default: () => [] },
  feedsById: { type: Object, default: () => ({}) },
  keepingGroupId: { type: Number, default: null },
});

defineEmits(['keep', 'dismiss']);

function getFeedPlatform(feedId) {
  const feed = props.feedsById[feedId];
  if (!feed) return '';
  return feed.type ? feed.type.charAt(0).toUpperCase() + feed.type.slice(1) : '';
}

function matchTypeLabel(type) {
  switch (type) {
    case 'url': return 'Same link';
    case 'video_url': return 'Same video';
    case 'text': return 'Similar text';
    default: return type;
  }
}
</script>
