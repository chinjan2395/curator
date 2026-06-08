<template>
  <div class="space-y-4">
    <AppPageHeader title="Content calendar" subtitle="Schedule native social posts." icon="calendar">
      <template #actions>
        <AppButton size="sm" @click="showForm = !showForm">Schedule post</AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="publish" />

    <AppLoader v-if="loading" label="Loading calendar…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <AppCard v-if="showForm">
        <template #header>
          <AppTitle size="sm">Schedule post</AppTitle>
          <p class="text-sm text-slate-500 mt-1">Choose where to publish, optional content, and when it should go live.</p>
        </template>

        <form class="space-y-4" @submit.prevent="submitSchedule">
          <AppFormField label="Social account" required id="schedule-credential">
            <AppSelect
              id="schedule-credential"
              v-model="form.social_credential_id"
              select-class="w-full"
              :show-placeholder="false"
            >
              <option value="">Select account</option>
              <option v-for="c in credentials" :key="c.id" :value="String(c.id)">{{ platformOptionLabel(c) }}</option>
            </AppSelect>
          </AppFormField>

          <AppFormField
            label="Content package"
            hint="Optional — link an approved package from Campaigns."
            id="schedule-package"
          >
            <AppSelect
              id="schedule-package"
              v-model="form.content_package_id"
              select-class="w-full"
              :show-placeholder="false"
            >
              <option value="">No content package</option>
              <option v-for="pkg in approvedPackages" :key="pkg.id" :value="String(pkg.id)">
                {{ packageLabel(pkg) }}
              </option>
            </AppSelect>
          </AppFormField>

          <AppFormField
            label="Publish time"
            required
            hint="Shown and scheduled in your local timezone."
            id="schedule-at"
          >
            <AppInput
              id="schedule-at"
              v-model="form.scheduled_at"
              type="datetime-local"
              input-class="w-full"
              :min="minScheduleAt"
            />
          </AppFormField>

          <div class="flex flex-wrap items-center justify-end gap-2 pt-4 border-t border-slate-200">
            <AppButton variant="secondary" type="button" @click="closeForm">Cancel</AppButton>
            <AppButton
              variant="primary"
              type="submit"
              :disabled="scheduling || !form.social_credential_id || !form.scheduled_at"
              :loading="scheduling"
            >
              {{ scheduling ? 'Scheduling…' : 'Schedule post' }}
            </AppButton>
          </div>
        </form>
      </AppCard>

      <AppCard class="p-4">
        <AppEmptyState
          v-if="!posts.length"
          title="No scheduled posts"
          description="Approve a content package in Campaigns, then schedule it here for native publishing."
          icon="calendar"
        >
          <AppButton size="sm" @click="showForm = true">Schedule post</AppButton>
        </AppEmptyState>
        <div v-else>
          <div v-for="group in grouped" :key="group.day" class="mb-4">
            <div class="text-xs font-semibold text-slate-500 mb-2">{{ formatLocalDayLabel(group.day) }}</div>
            <div v-for="post in group.items" :key="post.id" class="flex justify-between text-sm border-b border-slate-100 py-2">
              <span class="flex flex-wrap items-center gap-2">
                <span>{{ formatScheduledAt(post.scheduled_at) }}</span>
                <SocialPlatformLabel
                  v-if="post.social_credential?.provider"
                  :type="post.social_credential.provider"
                  size="sm"
                />
              </span>
              <span class="flex items-center gap-2">
                <span :class="statusClass(post.status)">{{ post.status }}</span>
                <button v-if="post.status === 'scheduled'" class="text-xs text-red-600" @click="cancel(post)">Cancel</button>
              </span>
            </div>
          </div>
        </div>
      </AppCard>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppFormField, AppInput, AppLoader, AppSelect, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { getPlatformLabel } from '../constants/socialPlatforms';
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

function statusClass(status) {
  if (status === 'failed') return 'text-red-600';
  if (status === 'published') return 'text-emerald-600';
  return 'text-slate-500';
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

onMounted(load);
</script>
