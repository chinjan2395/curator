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
      <AppCard v-if="showForm" class="p-4 space-y-3 max-w-lg">
        <AppSelect v-model="form.social_credential_id" select-class="w-full" :show-placeholder="false">
          <option value="">Select account</option>
          <option v-for="c in credentials" :key="c.id" :value="String(c.id)">{{ c.provider }} — {{ c.account_label || c.account_id }}</option>
        </AppSelect>
        <AppSelect v-model="form.content_package_id" select-class="w-full" :show-placeholder="false">
          <option value="">Content package (optional)</option>
          <option v-for="pkg in approvedPackages" :key="pkg.id" :value="String(pkg.id)">
            {{ packageLabel(pkg) }}
          </option>
        </AppSelect>
        <AppInput v-model="form.scheduled_at" type="datetime-local" input-class="w-full" />
        <AppButton @click="submitSchedule" :disabled="scheduling || !form.social_credential_id || !form.scheduled_at">
          {{ scheduling ? 'Scheduling…' : 'Schedule' }}
        </AppButton>
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
            <div class="text-xs font-semibold text-slate-500 mb-2">{{ group.day }}</div>
            <div v-for="post in group.items" :key="post.id" class="flex justify-between text-sm border-b border-slate-100 py-2">
              <span>{{ post.scheduled_at }} · {{ post.social_credential?.provider }}</span>
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
import { AppAlert, AppButton, AppCard, AppEmptyState, AppInput, AppLoader, AppSelect } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';

const route = useRoute();
const toast = useToastStore();
const posts = ref([]);
const credentials = ref([]);
const approvedPackages = ref([]);
const loading = ref(true);
const error = ref(null);
const showForm = ref(false);
const scheduling = ref(false);
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
    const day = (p.scheduled_at || '').slice(0, 10);
    if (!map[day]) map[day] = [];
    map[day].push(p);
  }
  return Object.keys(map).sort().map((day) => ({ day, items: map[day] }));
});

function packageLabel(pkg) {
  const name = pkg.campaign?.name || 'Campaign';
  const cap = (pkg.caption || '').slice(0, 40);
  return `${name} · ${pkg.platform} · ${cap}${cap.length >= 40 ? '…' : ''}`;
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
    const [cal, creds, pkgs] = await Promise.all([
      axios.get('/api/schedule/calendar', { skipErrorToast: true }),
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

async function submitSchedule() {
  scheduling.value = true;
  try {
    await axios.post('/api/schedule', {
      social_credential_id: Number(form.value.social_credential_id),
      content_package_id: form.value.content_package_id ? Number(form.value.content_package_id) : null,
      scheduled_at: form.value.scheduled_at,
    });
    toast.success('Post scheduled');
    showForm.value = false;
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
