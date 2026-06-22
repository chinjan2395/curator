<template>
  <div class="space-y-4">
    <AppPageHeader title="Content calendar" subtitle="Schedule native social posts." icon="calendar">
      <template #actions>
        <AppButton size="sm" @click="showForm = !showForm">
          <AppIcon name="add" class="w-3.5 h-3.5 mr-1.5" />
          Schedule post
        </AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="publish" />

    <AppLoader v-if="loading" label="Loading calendar…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <!-- Stats bar -->
      <div v-if="posts.length" class="grid grid-cols-3 gap-3">
        <div class="sched-stat-card">
          <div class="sched-stat-icon" style="background:#eff6ff;color:#3b82f6">
            <AppIcon name="send" class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xl font-bold text-slate-800 leading-none">{{ scheduledCount }}</p>
            <p class="text-2xs text-slate-500 mt-0.5">Scheduled</p>
          </div>
        </div>
        <div class="sched-stat-card">
          <div class="sched-stat-icon" style="background:#ecfdf5;color:#10b981">
            <AppIcon name="check" class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xl font-bold text-slate-800 leading-none">{{ publishedCount }}</p>
            <p class="text-2xs text-slate-500 mt-0.5">Published</p>
          </div>
        </div>
        <div class="sched-stat-card">
          <div class="sched-stat-icon" style="background:#fef2f2;color:#ef4444">
            <AppIcon name="alert" class="w-4 h-4" />
          </div>
          <div>
            <p class="text-xl font-bold text-slate-800 leading-none">{{ failedCount }}</p>
            <p class="text-2xs text-slate-500 mt-0.5">Failed</p>
          </div>
        </div>
      </div>

      <!-- Schedule form -->
      <AppCard v-if="showForm">
        <template #header>
          <div class="flex items-start gap-3">
            <div class="sched-form-icon-wrap">
              <AppIcon name="calendar" class="w-4 h-4 text-blue-600" />
            </div>
            <div>
              <AppTitle size="sm">Schedule post</AppTitle>
              <p class="text-sm text-slate-500 mt-0.5">Pick an account and approved content package. Requirements are checked before scheduling.</p>
            </div>
          </div>
        </template>

        <form class="space-y-4" @submit.prevent="submitSchedule">
          <div class="grid gap-4 sm:grid-cols-2">
            <AppFormField id="schedule-credential" label="Social account" required>
              <AppSelect
                id="schedule-credential"
                v-model="form.social_credential_id"
                select-class="w-full"
                :show-placeholder="false"
                @update:model-value="onCredentialChange"
              >
                <option value="">Select account</option>
                <option v-for="c in credentials" :key="c.id" :value="String(c.id)">{{ platformOptionLabel(c) }}</option>
              </AppSelect>
            </AppFormField>

            <AppFormField
              id="schedule-at"
              label="Publish time"
              required
              hint="Shown and scheduled in your local timezone."
            >
              <AppInput
                id="schedule-at"
                v-model="form.scheduled_at"
                type="datetime-local"
                input-class="w-full"
                :min="minScheduleAt"
              />
            </AppFormField>
          </div>

          <ScheduleValidationPanel
            v-if="selectedSchedulePlatform"
            :platform="selectedSchedulePlatform"
            :content-package="selectedPackage"
          />

          <AppFormField
            id="schedule-package"
            label="Content package"
            :hint="packageFieldHint"
            required
          >
            <AppSelect
              id="schedule-package"
              v-model="form.content_package_id"
              select-class="w-full"
              :show-placeholder="false"
            >
              <option value="">Select content package</option>
              <option
                v-for="pkg in orderedPackages"
                :key="pkg.id"
                :value="String(pkg.id)"
                :disabled="selectedSchedulePlatform && !isPackageCompatible(pkg, selectedSchedulePlatform)"
              >
                {{ packageOptionLabel(pkg) }}
              </option>
            </AppSelect>
          </AppFormField>

          <div class="flex flex-wrap items-center justify-end gap-2 pt-4 border-t border-slate-100">
            <AppButton variant="secondary" type="button" @click="closeForm">Cancel</AppButton>
            <AppButton
              variant="primary"
              type="submit"
              :disabled="!canSubmitSchedule"
              :loading="scheduling"
            >
              <AppIcon name="send" class="w-3.5 h-3.5 mr-1.5" />
              {{ scheduling ? 'Scheduling…' : 'Schedule post' }}
            </AppButton>
          </div>
        </form>
      </AppCard>

      <!-- Post list -->
      <div class="sched-list-card">
        <AppEmptyState
          v-if="!posts.length"
          title="No scheduled posts"
          description="Approve a content package in Campaigns, then schedule it here for native publishing."
          icon="calendar"
        >
          <AppButton size="sm" @click="showForm = true">
            <AppIcon name="add" class="w-3.5 h-3.5 mr-1.5" />
            Schedule post
          </AppButton>
        </AppEmptyState>

        <div v-else>
          <div v-for="group in grouped" :key="group.day">
            <div class="sched-day-header">
              <AppIcon name="calendar" class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
              <span>{{ formatLocalDayLabel(group.day) }}</span>
              <span class="sched-day-badge">{{ group.items.length }} post{{ group.items.length === 1 ? '' : 's' }}</span>
            </div>

            <div
              v-for="post in group.items"
              :key="post.id"
              class="sched-post-row"
            >
              <div class="sched-post-accent" :class="postAccentClass(displayStatus(post))" />
              <div class="sched-post-main">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="sched-post-time">
                    <AppIcon name="clock" class="w-3.5 h-3.5 text-slate-400" />
                    <template v-if="displayStatus(post) === 'published' && post.published_at">
                      Published {{ formatScheduledAt(post.published_at) }}
                    </template>
                    <template v-else-if="displayStatus(post) === 'retrying'">
                      Next attempt {{ formatScheduledAt(post.scheduled_at) }}
                    </template>
                    <template v-else>
                      Scheduled for {{ formatScheduledAt(post.scheduled_at) }}
                    </template>
                  </div>
                  <SocialPlatformLabel
                    v-if="post.social_credential?.provider"
                    :type="post.social_credential.provider"
                    size="sm"
                  />
                </div>
                <p v-if="post.content_package?.caption" class="sched-post-caption">
                  {{ post.content_package.caption.slice(0, 90) }}{{ post.content_package.caption.length > 90 ? '…' : '' }}
                </p>
                <p v-if="post.retry_count > 0" class="sched-post-retry">
                  Attempt {{ post.retry_count }} of 3
                  <span v-if="displayStatus(post) === 'retrying'">— retrying automatically in 15 minutes</span>
                  <span v-else-if="post.status === 'failed'">— no more automatic retries</span>
                </p>
                <p v-if="post.error_message" class="sched-post-error">{{ post.error_message }}</p>
              </div>
              <div class="sched-post-side">
                <AppBadge :variant="statusBadgeVariant(displayStatus(post))">{{ statusLabel(post) }}</AppBadge>
                <button
                  v-if="post.status === 'scheduled'"
                  class="sched-cancel-btn"
                  title="Cancel this scheduled post"
                  @click="cancel(post)"
                >
                  <AppIcon name="close" class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { useRealtimeWithFallback } from '../composables/useRealtimeWithFallback';
import { AppAlert, AppBadge, AppButton, AppCard, AppEmptyState, AppFormField, AppIcon, AppInput, AppLoader, AppSelect, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import ScheduleValidationPanel from '../components/ScheduleValidationPanel.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { getPlatformLabel } from '../constants/socialPlatforms';
import {
  isPackageCompatible,
  validateScheduleContent,
} from '../utils/scheduleContentValidation';
import {
  formatLocalDayLabel,
  formatScheduledAt,
  localDatetimeInputToUtcIso,
  localDayKeyFromUtcIso,
  localMonthUtcRange,
  minLocalDatetimeInputValue,
} from '../utils/datetime';

const route = useRoute();
const toast = useToastStore();
const posts = ref([]);
const credentials = ref([]);
const approvedPackages = ref([]);
const loading = ref(true);
const error = ref(null);
const showForm = ref(false);
const scheduling = ref(false);
const minScheduleAt = minLocalDatetimeInputValue();
const form = ref({
  social_credential_id: '',
  content_package_id: route.query.content_package_id ? String(route.query.content_package_id) : '',
  scheduled_at: '',
});

watch(
  () => route.query.content_package_id,
  (id) => {
    if (id) {
      form.value.content_package_id = String(id);
      showForm.value = true;
    }
  },
);

const grouped = computed(() => {
  const map = {};
  for (const p of posts.value) {
    const day = localDayKeyFromUtcIso(p.scheduled_at);
    if (!map[day]) map[day] = [];
    map[day].push(p);
  }
  return Object.keys(map).sort().map((day) => ({ day, items: map[day] }));
});

const scheduledCount = computed(() => posts.value.filter((p) => displayStatus(p) === 'scheduled').length);
const publishedCount = computed(() => posts.value.filter((p) => p.status === 'published').length);
const failedCount = computed(() => posts.value.filter((p) => ['failed', 'retrying'].includes(displayStatus(p))).length);

const selectedSchedulePlatform = computed(() => {
  const id = Number(form.value.social_credential_id);
  if (!id) return null;
  const cred = credentials.value.find((c) => c.id === id);
  return cred?.provider ?? null;
});

const selectedPackage = computed(() => {
  const id = Number(form.value.content_package_id);
  if (!id) return null;
  return approvedPackages.value.find((p) => p.id === id) ?? null;
});

const scheduleValidation = computed(() => {
  if (!selectedSchedulePlatform.value || !selectedPackage.value) {
    return { valid: false, checks: [] };
  }
  return validateScheduleContent(selectedPackage.value, selectedSchedulePlatform.value);
});

const canSubmitSchedule = computed(() => (
  !scheduling.value
  && form.value.social_credential_id
  && form.value.content_package_id
  && form.value.scheduled_at
  && scheduleValidation.value.valid
));

const packageFieldHint = computed(() => {
  if (!selectedSchedulePlatform.value) {
    return 'Select a social account first to see platform requirements.';
  }
  const compatible = approvedPackages.value.filter((p) => isPackageCompatible(p, selectedSchedulePlatform.value)).length;
  return compatible > 0
    ? `${compatible} compatible approved package${compatible === 1 ? '' : 's'} available. Incompatible options are disabled.`
    : 'No compatible approved packages — add media in Campaigns (e.g. Instagram requires an image URL).';
});

const orderedPackages = computed(() => {
  const platform = selectedSchedulePlatform.value;
  const list = [...approvedPackages.value];
  if (!platform) return list;
  return list.sort((a, b) => {
    const aOk = isPackageCompatible(a, platform);
    const bOk = isPackageCompatible(b, platform);
    if (aOk === bOk) return 0;
    return aOk ? -1 : 1;
  });
});

function onCredentialChange() {
  if (!selectedPackage.value || !selectedSchedulePlatform.value) return;
  if (!isPackageCompatible(selectedPackage.value, selectedSchedulePlatform.value)) {
    form.value.content_package_id = '';
  }
}

function packageOptionLabel(pkg) {
  const base = packageLabel(pkg);
  if (!selectedSchedulePlatform.value) return base;
  return isPackageCompatible(pkg, selectedSchedulePlatform.value)
    ? `${base} · ready`
    : `${base} · incompatible`;
}

function displayStatus(post) {
  if (post.status === 'failed') return 'failed';
  if (post.status === 'published') return 'published';
  if (post.status === 'scheduled' && post.retry_count > 0) return 'retrying';
  return 'scheduled';
}

function statusLabel(post) {
  const map = { failed: 'Failed', published: 'Published', retrying: 'Retrying', scheduled: 'Scheduled' };
  return map[displayStatus(post)];
}

function packageLabel(pkg) {
  const name = pkg.campaign?.name || 'Campaign';
  const cap = (pkg.caption || '').slice(0, 40);
  const platform = getPlatformLabel(pkg.platform);
  return `${name} · ${platform} · ${cap}${cap.length >= 40 ? '…' : ''}`;
}

function platformOptionLabel(credential) {
  const platform = getPlatformLabel(credential.provider);
  const account = credential.account_label || credential.account_id;
  return `${platform} — ${account}`;
}

function statusBadgeVariant(status) {
  if (status === 'failed') return 'danger';
  if (status === 'published') return 'success';
  if (status === 'retrying') return 'warning';
  return 'info';
}

function postAccentClass(status) {
  if (status === 'failed') return 'sched-post-accent--red';
  if (status === 'published') return 'sched-post-accent--green';
  if (status === 'retrying') return 'sched-post-accent--orange';
  return 'sched-post-accent--blue';
}

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const monthRange = localMonthUtcRange();
    const [cal, creds, pkgs] = await Promise.all([
      axios.get('/api/schedule/calendar', {
        params: { from: monthRange.from, to: monthRange.to },
        skipErrorToast: true,
      }),
      axios.get('/api/social-credentials', { skipErrorToast: true }),
      axios.get('/api/content-packages', { params: { status: 'approved' }, skipErrorToast: true }),
    ]);
    posts.value = cal.data.data || cal.data || [];
    credentials.value = creds.data.data || creds.data || [];
    approvedPackages.value = pkgs.data.data || pkgs.data || [];
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load calendar';
  } finally {
    loading.value = false;
  }
}

function closeForm() {
  showForm.value = false;
}

async function submitSchedule() {
  if (!canSubmitSchedule.value) {
    toast.error('Fix the content requirements above before scheduling.');
    return;
  }
  scheduling.value = true;
  try {
    await axios.post('/api/schedule', {
      social_credential_id: Number(form.value.social_credential_id),
      content_package_id: form.value.content_package_id ? Number(form.value.content_package_id) : null,
      scheduled_at: localDatetimeInputToUtcIso(form.value.scheduled_at),
    });
    toast.success('Post scheduled');
    closeForm();
    await load();
  } finally {
    scheduling.value = false;
  }
}

async function cancel(post) {
  try {
    await axios.delete(`/api/schedule/${post.id}`);
    toast.success('Schedule cancelled');
    await load();
  } catch {
    // interceptor toasts
  }
}

function applyCalendarPost(post) {
  if (!post?.id) return;
  if (post.status === 'cancelled') {
    posts.value = posts.value.filter((p) => p.id !== post.id);
    return;
  }
  const idx = posts.value.findIndex((p) => p.id === post.id);
  if (idx !== -1) {
    posts.value[idx] = post;
  } else if (['scheduled', 'failed', 'published'].includes(post.status)) {
    posts.value = [post, ...posts.value];
  }
}

useRealtimeWithFallback({
  event: 'scheduledPost',
  onEvent: ({ post }) => applyCalendarPost(post),
  poll: () => load(),
});

onMounted(load);
</script>

<style scoped>
.sched-stat-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
}

.sched-stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.625rem;
  flex-shrink: 0;
}

.sched-form-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.5rem;
  background: #eff6ff;
  flex-shrink: 0;
}

.sched-list-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  overflow: hidden;
}

.sched-day-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 1rem;
  background: #f8fafc;
  border-bottom: 1px solid #eef2f6;
  font-size: 0.78rem;
  font-weight: 600;
  color: #475569;
}

.sched-day-badge {
  margin-left: auto;
  font-size: 0.68rem;
  font-weight: 500;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
}

.sched-post-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.12s ease;
}

.sched-post-row:last-child {
  border-bottom: none;
}

.sched-post-row:hover {
  background: rgba(248, 250, 252, 0.8);
}

.sched-post-accent {
  width: 3px;
  min-height: 2.25rem;
  align-self: stretch;
  border-radius: 999px;
  flex-shrink: 0;
}

.sched-post-accent--blue   { background: #3b82f6; }
.sched-post-accent--green  { background: #10b981; }
.sched-post-accent--red    { background: #ef4444; }
.sched-post-accent--orange { background: #f59e0b; }

.sched-post-main {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.25rem;
}

.sched-post-time {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
}

.sched-post-caption {
  font-size: 0.75rem;
  color: #64748b;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.sched-post-retry {
  font-size: 0.72rem;
  color: #b45309;
}

.sched-post-error {
  font-size: 0.72rem;
  color: #dc2626;
  line-height: 1.4;
  word-break: break-word;
}

.sched-post-side {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.sched-cancel-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 0.375rem;
  color: #ef4444;
  transition: background 0.12s ease;
}

.sched-cancel-btn:hover {
  background: #fee2e2;
}
</style>
