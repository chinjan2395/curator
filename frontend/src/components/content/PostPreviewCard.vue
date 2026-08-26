<template>
  <div class="pp-device">
    <!-- Facebook -->
    <article v-if="platform === 'facebook'" class="pp-fb">
      <header class="pp-fb-head">
        <span class="pp-avatar pp-avatar--fb">{{ initials }}</span>
        <div class="min-w-0">
          <p class="pp-fb-name">{{ accountName }}</p>
          <p class="pp-fb-meta">Just now · <SvgGlobe /></p>
        </div>
      </header>

      <p class="pp-fb-caption" v-html="linkified"></p>

      <div class="pp-media pp-media--fb">
        <img v-if="mediaUrl" :src="mediaUrl" alt="" class="pp-media-img" />
        <div v-else class="pp-media-empty"><AppIcon name="image" class="w-6 h-6" /></div>
      </div>

      <div class="pp-fb-stats">
        <span>👍❤️ 128</span>
        <span>24 comments · 6 shares</span>
      </div>
      <div class="pp-fb-actions">
        <div class="pp-fb-action"><SvgThumbsUp /> Like</div>
        <div class="pp-fb-action"><SvgComment /> Comment</div>
        <div class="pp-fb-action"><SvgShare /> Share</div>
      </div>
    </article>

    <!-- Instagram -->
    <article v-else-if="platform === 'instagram'" class="pp-ig">
      <header class="pp-ig-head">
        <span class="pp-avatar pp-avatar--ig">{{ initials }}</span>
        <p class="pp-ig-name">{{ handle }}</p>
        <span class="pp-ig-more">···</span>
      </header>

      <div class="pp-media pp-media--ig">
        <img v-if="mediaUrl" :src="mediaUrl" alt="" class="pp-media-img" />
        <div v-else class="pp-media-empty"><AppIcon name="image" class="w-6 h-6" /></div>
      </div>

      <div class="pp-ig-actions">
        <SvgHeart /> <SvgComment /> <SvgSend />
        <span class="pp-ig-actions-spacer"></span>
        <SvgBookmark />
      </div>

      <p class="pp-ig-likes">142 likes</p>
      <p class="pp-ig-caption">
        <span class="pp-ig-name">{{ handle }}</span>
        <span v-html="linkified"></span>
      </p>
      <p class="pp-ig-viewcomments">View all 18 comments</p>
      <p class="pp-ig-time">A FEW SECONDS AGO</p>
    </article>

    <!-- Twitter / X -->
    <article v-else class="pp-tw">
      <header class="pp-tw-head">
        <span class="pp-avatar pp-avatar--tw">{{ initials }}</span>
        <div class="min-w-0">
          <p class="pp-tw-name">
            {{ accountName }} <SvgVerified class="pp-tw-verified" />
            <span class="pp-tw-handle">{{ handle }} · now</span>
          </p>
        </div>
      </header>

      <p class="pp-tw-caption" v-html="linkified"></p>

      <div v-if="mediaUrl" class="pp-media pp-media--tw">
        <img :src="mediaUrl" alt="" class="pp-media-img" />
      </div>

      <div class="pp-tw-actions">
        <span class="pp-tw-action"><SvgComment /> 18</span>
        <span class="pp-tw-action"><SvgRetweet /> 9</span>
        <span class="pp-tw-action"><SvgHeart /> 96</span>
        <span class="pp-tw-action"><SvgShare /></span>
      </div>
    </article>
  </div>
</template>

<script setup>
import { computed, h } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { AppIcon } from '../ui';

const props = defineProps({
  platform: { type: String, required: true },
  caption: { type: String, default: '' },
  hashtags: { type: Array, default: () => [] },
  mediaUrl: { type: String, default: null },
});

const auth = useAuthStore();

const accountName = computed(() => auth.user?.name?.trim() || 'Your Page');
const handle = computed(() => '@' + (auth.user?.name || 'yourpage').toLowerCase().replace(/[^a-z0-9]+/g, ''));
const initials = computed(() => {
  const name = String(auth.user?.name || '').trim();
  if (!name) return 'U';
  const parts = name.split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
});

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

const linkified = computed(() => {
  const caption = escapeHtml(props.caption || '');
  const withTags = caption.replace(/(#[\p{L}0-9_]+)/gu, '<span class="pp-tag">$1</span>');
  const hashtags = (props.hashtags || [])
    .map((tag) => `<span class="pp-tag">${escapeHtml(tag)}</span>`)
    .join(' ');
  return hashtags ? `${withTags}<br><span class="pp-tag-line">${hashtags}</span>` : withTags;
});

// Small decorative brand icons — kept local since they're preview-only chrome,
// not part of the app's shared AppIcon set.
const SvgGlobe = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm5.9 7.25h-2.65a12.8 12.8 0 0 0-.9-4.2 6.53 6.53 0 0 1 3.55 4.2ZM10 3.5c.7.9 1.5 2.7 1.7 5.75H8.3c.2-3.05 1-4.85 1.7-5.75Zm-2.35.55a12.8 12.8 0 0 0-.9 4.2H4.1a6.53 6.53 0 0 1 3.55-4.2ZM4.1 10.75h2.65c.1 1.5.4 2.95.9 4.2a6.53 6.53 0 0 1-3.55-4.2Zm3.9 0h3.4c-.2 3.05-1 4.85-1.7 5.75-.7-.9-1.5-2.7-1.7-5.75Zm4.65 4.2c.5-1.25.8-2.7.9-4.2h2.65a6.53 6.53 0 0 1-3.55 4.2Z' }),
]);
const SvgThumbsUp = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M2 8.5A1.5 1.5 0 0 1 3.5 7h1a1.5 1.5 0 0 1 1.5 1.5v6A1.5 1.5 0 0 1 4.5 16h-1A1.5 1.5 0 0 1 2 14.5v-6ZM8.4 7.2 9 3.6a1.4 1.4 0 0 1 2.76.36v2.3h2.9A1.6 1.6 0 0 1 16.2 8l-.9 5.4a1.8 1.8 0 0 1-1.78 1.5H8.7a1.5 1.5 0 0 1-1.5-1.5V8.3a1.5 1.5 0 0 1 .2-.75Z' }),
]);
const SvgComment = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M10 2.5c-4.4 0-8 2.86-8 6.4 0 2.1 1.28 3.96 3.26 5.13-.14.9-.5 2-1.26 2.9 1.4.1 2.9-.42 4.06-1.24.62.14 1.28.21 1.94.21 4.4 0 8-2.86 8-6.4s-3.6-6.4-8-6.4Z' }),
]);
const SvgShare = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M13.5 3a2 2 0 1 0 .3 3.98l-4.6 2.65a2 2 0 1 0 0 2.74l4.6 2.65A2 2 0 1 0 15 13.65l-4.6-2.65a2 2 0 0 0 0-2l4.6-2.65A2 2 0 0 0 13.5 3Z' }),
]);
const SvgHeart = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M10 17.3s-6.3-3.8-8.3-7.6C.4 7 1.7 3.8 4.8 3.2c1.8-.35 3.6.5 4.6 1.9a.7.7 0 0 0 1.2 0c1-1.4 2.8-2.25 4.6-1.9 3.1.6 4.4 3.8 3.1 6.5-2 3.8-8.3 7.6-8.3 7.6Z' }),
]);
const SvgSend = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M2.9 9.65 17 3l-5.3 14.6-2.5-5.9-5.9-2.05Z' }),
]);
const SvgBookmark = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M5.5 2.5h9a1 1 0 0 1 1 1v14l-5.5-3.4-5.5 3.4v-14a1 1 0 0 1 1-1Z' }),
]);
const SvgRetweet = () => h('svg', { viewBox: '0 0 20 20', fill: 'currentColor', class: 'pp-inline-icon' }, [
  h('path', { d: 'M5.5 3v8.5H3.8L6.5 15l2.7-3.5H7.5V5H13V3H5.5ZM14.5 17v-8.5h1.7L13.5 5l-2.7 3.5h1.7V15H6v2h8.5Z' }),
]);
const SvgVerified = { render: () => h('svg', { viewBox: '0 0 20 20', fill: '#1d9bf0', class: 'pp-inline-icon-sm' }, [
  h('path', { d: 'm10 1.5 1.9 1 2.1-.5 1.1 1.9 2.1.6.1 2.2 1.5 1.6-1.5 1.6-.1 2.2-2.1.6-1.1 1.9-2.1-.5-1.9 1-1.9-1-2.1.5-1.1-1.9-2.1-.6-.1-2.2L2.3 10l1.5-1.6.1-2.2 2.1-.6L7.1 4l2.1.5L10 1.5Zm-1 10.9 4.3-4.3-1-1-3.3 3.3-1.5-1.5-1 1L9 12.4Z' }),
]) };
</script>

<style scoped>
.pp-device { width: 100%; max-width: 26rem; }

.pp-avatar {
  flex-shrink: 0;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
}
.pp-avatar--fb { background: #1877f2; }
.pp-avatar--ig { background: linear-gradient(135deg, #f9ce34, #ee2a7b 50%, #6228d7); width: 2.25rem; height: 2.25rem; font-size: 0.7rem; }
.pp-avatar--tw { background: #0f172a; }

.pp-inline-icon { width: 1rem; height: 1rem; }
.pp-inline-icon-sm { width: 0.9rem; height: 0.9rem; display: inline; vertical-align: -0.1em; }

.pp-media { border-radius: 0.5rem; overflow: hidden; background: #e2e8f0; }
.pp-media-img { width: 100%; display: block; object-fit: cover; }
.pp-media-empty { display: flex; align-items: center; justify-content: center; color: #94a3b8; aspect-ratio: 4 / 3; }

:deep(.pp-tag) { color: #1d4ed8; font-weight: 500; }
:deep(.pp-tag-line) { display: inline-block; margin-top: 0.25rem; }

/* Facebook */
.pp-fb { background: #fff; border: 1px solid #dbdfe3; border-radius: 0.5rem; padding: 0.75rem; font-size: 0.875rem; color: #050505; }
.pp-fb-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.pp-fb-name { font-weight: 600; font-size: 0.8125rem; }
.pp-fb-meta { font-size: 0.6875rem; color: #65676b; display: flex; align-items: center; gap: 0.25rem; }
.pp-fb-caption { white-space: pre-wrap; line-height: 1.35; margin-bottom: 0.5rem; }
.pp-media--fb .pp-media-empty { aspect-ratio: 1.9 / 1; }
.pp-fb-stats { display: flex; justify-content: space-between; font-size: 0.6875rem; color: #65676b; padding: 0.5rem 0; border-bottom: 1px solid #ebedf0; }
.pp-fb-actions { display: flex; padding-top: 0.25rem; }
.pp-fb-action { flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.35rem; padding: 0.4rem 0; font-size: 0.75rem; font-weight: 600; color: #65676b; background: none; border: none; border-radius: 0.375rem; cursor: default; }

/* Instagram */
.pp-ig { background: #fff; border: 1px solid #dbdbdb; border-radius: 0.375rem; font-size: 0.8125rem; color: #262626; overflow: hidden; }
.pp-ig-head { display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0.75rem; }
.pp-ig-name { font-weight: 600; font-size: 0.8125rem; }
.pp-ig-more { margin-left: auto; color: #262626; font-weight: 700; }
.pp-media--ig .pp-media-empty { aspect-ratio: 1 / 1; }
.pp-ig-actions { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem 0.25rem; color: #262626; }
.pp-ig-actions-spacer { flex: 1; }
.pp-ig-likes { font-weight: 600; padding: 0 0.75rem; margin-top: 0.25rem; }
.pp-ig-caption { padding: 0.2rem 0.75rem 0; line-height: 1.35; }
.pp-ig-viewcomments { color: #8e8e8e; padding: 0.3rem 0.75rem 0; font-size: 0.75rem; }
.pp-ig-time { color: #8e8e8e; padding: 0.35rem 0.75rem 0.6rem; font-size: 0.625rem; letter-spacing: 0.02em; }

/* Twitter / X */
.pp-tw { background: #fff; border: 1px solid #eff3f4; border-radius: 1rem; padding: 0.75rem; font-size: 0.875rem; color: #0f1419; }
.pp-tw-head { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.4rem; }
.pp-tw-name { font-weight: 700; font-size: 0.875rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.3rem; }
.pp-tw-handle { font-weight: 400; color: #536471; font-size: 0.8125rem; }
.pp-tw-caption { white-space: pre-wrap; line-height: 1.35; margin-bottom: 0.5rem; }
.pp-media--tw { border: 1px solid #cfd9de; border-radius: 1rem; }
.pp-media--tw .pp-media-img { max-height: 16rem; }
.pp-tw-actions { display: flex; justify-content: space-between; max-width: 22rem; margin-top: 0.6rem; color: #536471; font-size: 0.75rem; }
.pp-tw-action { display: flex; align-items: center; gap: 0.3rem; }
</style>
