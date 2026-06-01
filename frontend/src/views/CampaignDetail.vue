<template>
  <div class="space-y-4">
    <AppLoader v-if="loading" label="Loading campaign…" />

    <template v-else-if="loadError">
      <AppPageHeader title="Campaign" subtitle="Could not load this campaign." icon="megaphone" />
      <AppAlert variant="danger">{{ loadError }}</AppAlert>
      <AppButton @click="$router.push('/campaigns')">Back to campaigns</AppButton>
    </template>

    <template v-else-if="campaign">
      <AppPageHeader :title="campaign.name" subtitle="Campaign content studio" icon="megaphone">
        <template #actions>
          <AppButton variant="primary" @click="generate" :disabled="generating">
            {{ generating ? 'Generating…' : 'Generate content' }}
          </AppButton>
        </template>
      </AppPageHeader>

      <CapabilityBanner context="ai" />

      <AppEmptyState
        v-if="!packages.length"
        title="No content packages yet"
        description="Generate AI drafts for each platform in this campaign."
        icon="megaphone"
      >
        <AppButton variant="primary" :disabled="generating" @click="generate">Generate content</AppButton>
      </AppEmptyState>

      <div v-else class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3">
          <AppCard v-for="pkg in packages" :key="pkg.id" class="p-4 space-y-3">
            <div class="flex justify-between items-start gap-2">
              <div class="text-xs uppercase text-slate-500">{{ pkg.platform }} · v{{ pkg.version }}</div>
              <AppSelect v-model="pkg.status" select-class="!py-1 !text-xs" :show-placeholder="false" @change="updateStatus(pkg)">
                <option value="draft">Draft</option>
                <option value="in_review">In review</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
              </AppSelect>
            </div>
            <p class="text-sm text-slate-800 whitespace-pre-wrap">{{ pkg.caption }}</p>
            <div class="text-xs text-slate-500">{{ (pkg.hashtags || []).join(' ') }}</div>

            <div v-if="(pkg.media_urls || []).length" class="text-xs text-slate-600 space-y-1">
              <div class="font-medium">Media URLs</div>
              <div v-for="(url, i) in pkg.media_urls" :key="i" class="truncate">{{ url }}</div>
            </div>

            <div class="flex flex-wrap gap-2 items-end">
              <AppSelect
                v-model="assetPick[pkg.id]"
                select-class="!py-1 !text-xs flex-1 min-w-[140px]"
                :show-placeholder="true"
                placeholder="Attach from library"
              >
                <option value="">Attach from library…</option>
                <option v-for="a in assets" :key="a.id" :value="a.url">{{ a.file_name }}</option>
              </AppSelect>
              <AppButton size="sm" variant="secondary" :disabled="!assetPick[pkg.id]" @click="attachAsset(pkg)">Attach</AppButton>
            </div>
            <div class="flex gap-2">
              <AppInput
                v-model="manualUrl[pkg.id]"
                placeholder="Or paste public HTTPS image URL"
                input-class="!text-xs flex-1"
              />
              <AppButton size="sm" @click="addManualUrl(pkg)">Add URL</AppButton>
            </div>

            <div class="flex gap-2">
              <AppButton size="sm" variant="secondary" @click="selectPackage(pkg)">Refine</AppButton>
              <AppButton v-if="pkg.status === 'approved'" size="sm" @click="schedulePackage(pkg)">Schedule</AppButton>
            </div>
          </AppCard>
        </div>

        <AppCard v-if="selected" class="p-4 space-y-3 h-fit">
          <AppTitle size="sm">Refine caption</AppTitle>
          <AppInput v-model="instruction" placeholder="e.g. Make it shorter and add a CTA" input-class="w-full" />
          <AppButton @click="refine" :disabled="refining">{{ refining ? 'Refining…' : 'Refine with AI' }}</AppButton>
          <div v-if="refinedCaption" class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-xs font-semibold text-slate-500 mb-1">Before</div>
              <p class="text-slate-700 bg-slate-50 p-2 rounded">{{ selected.caption }}</p>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-500 mb-1">After</div>
              <p class="text-slate-700 bg-emerald-50 p-2 rounded">{{ refinedCaption }}</p>
            </div>
          </div>
          <div v-if="versions.length" class="text-xs text-slate-500 space-y-1">
            <div class="font-semibold">Version history</div>
            <div v-for="v in versions" :key="v.id">v{{ v.version }} — {{ v.status }}</div>
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useCampaignsStore } from '../stores/campaigns';
import { useToastStore } from '../stores/toast';
import { AppAlert, AppButton, AppCard, AppEmptyState, AppInput, AppLoader, AppSelect, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';

const route = useRoute();
const router = useRouter();
const store = useCampaignsStore();
const toast = useToastStore();
const campaign = ref(null);
const loading = ref(true);
const loadError = ref(null);
const generating = ref(false);
const selected = ref(null);
const instruction = ref('');
const refining = ref(false);
const refinedCaption = ref('');
const versions = ref([]);
const assets = ref([]);
const assetPick = reactive({});
const manualUrl = reactive({});

const packages = computed(() => campaign.value?.content_packages || []);

onMounted(async () => {
  await Promise.all([load(), loadAssets()]);
});

async function load() {
  loading.value = true;
  loadError.value = null;
  try {
    campaign.value = await store.fetchOne(route.params.id);
  } catch (e) {
    loadError.value = store.error || e.response?.data?.message || 'Failed to load campaign';
  } finally {
    loading.value = false;
  }
}

async function loadAssets() {
  try {
    const { data } = await axios.get('/api/content/assets', { skipErrorToast: true });
    const payload = data.data?.data || data.data || [];
    assets.value = Array.isArray(payload) ? payload : [];
  } catch {
    assets.value = [];
  }
}

async function generate() {
  generating.value = true;
  try {
    await store.generate(route.params.id);
    await load();
  } finally {
    generating.value = false;
  }
}

function selectPackage(pkg) {
  selected.value = pkg;
  refinedCaption.value = '';
  loadVersions(pkg);
}

async function loadVersions(pkg) {
  try {
    const { data } = await axios.get(`/api/content-packages/${pkg.id}/versions`);
    versions.value = data.data || data || [];
  } catch {
    versions.value = [];
  }
}

async function refine() {
  if (!selected.value || !instruction.value) return;
  refining.value = true;
  try {
    const { data } = await axios.post(`/api/content-packages/${selected.value.id}/refine`, {
      instruction: instruction.value,
    });
    const refined = data.data || data;
    refinedCaption.value = refined.caption;
    toast.success('Caption refined');
    await load();
  } finally {
    refining.value = false;
  }
}

async function updateStatus(pkg) {
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/status`, { status: pkg.status });
    toast.success('Status updated');
  } catch {
    await load();
  }
}

async function saveMedia(pkg, urls) {
  await axios.patch(`/api/content-packages/${pkg.id}/media`, { media_urls: urls });
  toast.success('Media updated');
  await load();
}

async function attachAsset(pkg) {
  const url = assetPick[pkg.id];
  if (!url) return;
  const urls = [...(pkg.media_urls || []), url].slice(0, 4);
  await saveMedia(pkg, urls);
  assetPick[pkg.id] = '';
}

async function addManualUrl(pkg) {
  const url = (manualUrl[pkg.id] || '').trim();
  if (!url) return;
  const urls = [...(pkg.media_urls || []), url].slice(0, 4);
  await saveMedia(pkg, urls);
  manualUrl[pkg.id] = '';
}

function schedulePackage(pkg) {
  router.push({ path: '/calendar', query: { content_package_id: pkg.id } });
}
</script>
