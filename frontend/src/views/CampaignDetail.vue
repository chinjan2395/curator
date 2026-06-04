<template>
  <div class="space-y-4">
    <AppLoader v-if="loading" label="Loading campaign…" />

    <template v-else-if="loadError">
      <AppPageHeader title="Campaign" subtitle="Could not load this campaign." icon="megaphone" />
      <AppAlert variant="danger">{{ loadError }}</AppAlert>
      <AppButton @click="$router.push('/campaigns')">Back to campaigns</AppButton>
    </template>

    <template v-else-if="campaign">
      <AppPageHeader :title="campaign.name" :subtitle="pageSubtitle" icon="megaphone">
        <template #actions>
          <div class="flex flex-wrap gap-2">
            <AppButton
              v-if="activeTab === 'brief'"
              variant="secondary"
              :disabled="!canSaveCampaign"
              @click="saveCampaign"
            >
              {{ savingCampaign ? 'Saving…' : 'Save brief' }}
            </AppButton>
            <AppButton variant="primary" :disabled="generating || savingCampaign" @click="generate">
              {{ generating ? 'Generating…' : 'Generate content' }}
            </AppButton>
          </div>
        </template>
      </AppPageHeader>

      <CapabilityBanner context="ai" />

      <nav class="campaign-mode-tabs" aria-label="Campaign sections">
        <button
          type="button"
          class="campaign-mode-tab"
          :class="activeTab === 'brief' ? 'campaign-mode-tab--active' : ''"
          @click="activeTab = 'brief'"
        >
          Brief
        </button>
        <button
          type="button"
          class="campaign-mode-tab"
          :class="activeTab === 'drafts' ? 'campaign-mode-tab--active' : ''"
          @click="activeTab = 'drafts'"
        >
          Drafts
          <span v-if="packages.length" class="campaign-mode-tab__badge">{{ packages.length }}</span>
        </button>
      </nav>

      <!-- Brief -->
      <div v-show="activeTab === 'brief'" class="space-y-4">
        <AppCard padding="none" class="campaign-brief-card p-4">
          <form class="space-y-3" @submit.prevent="saveCampaign">
            <div class="campaign-form-panel">
              <p class="text-sm-pro font-semibold text-slate-800">Basics</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
                <AppFormField label="Campaign name" required>
                  <AppInput v-model="campaignForm.name" type="text" placeholder="Summer product launch" />
                </AppFormField>
                <AppFormField label="Tone">
                  <AppInput v-model="campaignForm.tone" type="text" placeholder="Professional" />
                </AppFormField>
              </div>
              <AppFormField label="Platforms" hint="Comma-separated: instagram, linkedin, tiktok" class="mt-3">
                <AppInput v-model="campaignForm.platformsText" type="text" placeholder="instagram, twitter, facebook" />
              </AppFormField>
            </div>

            <div class="campaign-form-panel">
              <p class="text-sm-pro font-semibold text-slate-800">Message</p>
              <div class="grid grid-cols-1 gap-3 mt-3">
                <AppFormField label="Product / service">
                  <AppInput
                    v-model="campaignForm.product_info"
                    type="textarea"
                    :rows="4"
                    placeholder="What are you promoting?"
                  />
                </AppFormField>
                <AppFormField label="Description (optional)">
                  <AppInput
                    v-model="campaignForm.description"
                    type="textarea"
                    :rows="3"
                    placeholder="Launch notes or extra context"
                  />
                </AppFormField>
              </div>
            </div>

            <div class="campaign-form-panel">
              <p class="text-sm-pro font-semibold text-slate-800">Audience & goals</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
                <AppFormField label="Target audience" hint="One per line or comma-separated">
                  <AppInput v-model="campaignForm.targetAudienceText" type="textarea" :rows="4" />
                </AppFormField>
                <AppFormField label="Goals" hint="One per line or comma-separated">
                  <AppInput v-model="campaignForm.goalsText" type="textarea" :rows="4" />
                </AppFormField>
              </div>
            </div>

            <div v-if="campaignError" class="text-2xs text-red-600">{{ campaignError }}</div>
          </form>
        </AppCard>
      </div>

      <!-- Drafts -->
      <div v-show="activeTab === 'drafts'" class="space-y-3">
        <div class="campaign-brief-summary">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-800">{{ briefSummaryLine }}</p>
            <p v-if="briefProductPreview" class="text-2xs text-slate-500 mt-1 truncate">{{ briefProductPreview }}</p>
          </div>
          <AppButton variant="secondary" size="sm" @click="activeTab = 'brief'">Edit brief</AppButton>
        </div>

        <AppEmptyState
          v-if="!packages.length"
          title="No drafts yet"
          description="Generate platform captions from your campaign brief."
          icon="megaphone"
        >
          <AppButton variant="primary" :disabled="generating" @click="generate">Generate content</AppButton>
        </AppEmptyState>

        <template v-else>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-2xs text-slate-500">
              {{ filteredPackages.length }} draft{{ filteredPackages.length === 1 ? '' : 's' }}
              <span v-if="approvedCount"> · {{ approvedCount }} approved</span>
            </p>
          </div>

          <div v-if="draftPlatformOptions.length > 1" class="flex flex-wrap gap-2">
            <button
              v-for="opt in draftPlatformOptions"
              :key="opt.id"
              type="button"
              class="campaign-platform-chip"
              :class="platformFilter === opt.id ? 'campaign-platform-chip--active' : ''"
              @click="platformFilter = opt.id"
            >
              <SocialPlatformLabel
                v-if="opt.id !== 'all'"
                :type="opt.id"
                :suffix="` (${opt.count})`"
                size="sm"
              />
              <span v-else class="text-xs font-medium">All ({{ opt.count }})</span>
            </button>
          </div>

          <div class="space-y-2">
            <AppCard
              v-for="pkg in filteredPackages"
              :key="pkg.id"
              padding="none"
              class="campaign-draft-card"
              :class="expandedPackageId === pkg.id ? 'campaign-draft-card--expanded' : ''"
            >
              <div class="campaign-draft-row">
                <button type="button" class="campaign-draft-row__main" @click="toggleExpand(pkg.id)">
                  <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <SocialPlatformLabel :type="pkg.platform" size="md" />
                    <span class="text-2xs text-slate-500">v{{ pkg.version }}</span>
                    <span class="campaign-draft-status-badge">{{ formatStatusLabel(pkg.status) }}</span>
                  </div>
                  <p class="campaign-draft-preview">{{ pkg.caption }}</p>
                  <p v-if="pkg.hashtags?.length" class="text-2xs text-slate-500 truncate">{{ pkg.hashtags.join(' ') }}</p>
                </button>

                <div class="campaign-draft-row__side" @click.stop>
                  <AppSelect
                    :model-value="pkg.status"
                    select-class="!py-1.5 !text-xs min-w-[7.5rem]"
                    :show-placeholder="false"
                    @update:model-value="(value) => onPackageStatusChange(pkg, value)"
                  >
                    <option value="draft">Draft</option>
                    <option value="in_review">In review</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                  </AppSelect>
                  <div class="flex flex-wrap gap-1.5 justify-end">
                    <AppButton size="sm" variant="ghost" @click="toggleExpand(pkg.id)">
                      {{ expandedPackageId === pkg.id ? 'Collapse' : 'Expand' }}
                    </AppButton>
                    <AppButton size="sm" variant="secondary" @click="openRefine(pkg)">Refine</AppButton>
                    <AppButton v-if="pkg.status === 'approved'" size="sm" @click="schedulePackage(pkg)">Schedule</AppButton>
                  </div>
                </div>
              </div>

              <div v-if="expandedPackageId === pkg.id" class="campaign-draft-expanded">
                <p class="text-sm text-slate-800 whitespace-pre-wrap leading-6">{{ pkg.caption }}</p>

                <div v-if="(pkg.media_urls || []).length" class="campaign-media-list">
                  <span class="text-xs font-medium text-slate-700">Media ({{ pkg.media_urls.length }}/4)</span>
                  <div v-for="(url, i) in pkg.media_urls" :key="i" class="truncate font-mono text-2xs text-slate-600">{{ url }}</div>
                </div>

                <div class="campaign-media-block">
                  <div class="campaign-media-toggle">
                    <button
                      type="button"
                      class="campaign-media-toggle__btn"
                      :class="mediaModeFor(pkg.id) === 'library' ? 'campaign-media-toggle__btn--active' : ''"
                      @click="setMediaMode(pkg.id, 'library')"
                    >
                      Library
                    </button>
                    <button
                      type="button"
                      class="campaign-media-toggle__btn"
                      :class="mediaModeFor(pkg.id) === 'url' ? 'campaign-media-toggle__btn--active' : ''"
                      @click="setMediaMode(pkg.id, 'url')"
                    >
                      URL
                    </button>
                  </div>

                  <div v-if="mediaModeFor(pkg.id) === 'library'" class="flex flex-wrap gap-2 items-end">
                    <AppFormField label="Asset" class="flex-1 min-w-[12rem]">
                      <AppSelect
                        :model-value="assetPickValue(pkg.id)"
                        select-class="!py-2 !text-sm"
                        :show-placeholder="true"
                        placeholder="Choose asset"
                        @update:model-value="(value) => setAssetPick(pkg.id, value)"
                      >
                        <option
                          v-for="a in assetsForPlatform(pkg.platform)"
                          :key="a.id"
                          :value="String(a.id)"
                        >
                          {{ a.file_name }}{{ formatAssetTags(a) }}
                        </option>
                      </AppSelect>
                    </AppFormField>
                    <AppButton
                      size="sm"
                      variant="secondary"
                      :disabled="!assetPickValue(pkg.id)"
                      @click="attachAsset(pkg)"
                    >
                      Attach
                    </AppButton>
                    <p
                      v-if="!assetsForPlatform(pkg.platform).length"
                      class="w-full text-2xs text-amber-700"
                    >
                      No matching assets for this platform.
                      <router-link to="/content-library" class="underline font-medium">Upload in Content library</router-link>
                      or use the URL tab.
                    </p>
                  </div>

                  <div v-else class="flex flex-wrap gap-2 items-end">
                    <AppFormField label="Public URL" class="flex-1 min-w-[12rem]">
                      <AppInput
                        :model-value="manualUrlValue(pkg.id)"
                        placeholder="https://…"
                        input-class="!text-sm"
                        @update:model-value="(value) => setManualUrl(pkg.id, value)"
                      />
                    </AppFormField>
                    <AppButton size="sm" :disabled="!manualUrlValue(pkg.id).trim()" @click="addManualUrl(pkg)">Add</AppButton>
                  </div>
                </div>
              </div>
            </AppCard>
          </div>
        </template>
      </div>

      <AppModal
        :open="refineModalOpen"
        title="Refine caption"
        size="xl"
        @close="closeRefineModal"
      >
        <template v-if="selected">
          <SocialPlatformLabel :type="selected.platform" size="md" class="mb-3" />
          <AppFormField label="Refinement prompt" hint="e.g. Shorter, add CTA, more urgent">
            <AppInput v-model="instruction" type="textarea" :rows="4" placeholder="How should this caption change?" />
          </AppFormField>

          <div v-if="refinedCaption" class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-4 text-sm">
            <div class="campaign-compare-card">
              <div class="text-xs font-semibold text-slate-500 mb-1">Before</div>
              <p class="text-slate-700 whitespace-pre-wrap leading-6">{{ selected.caption }}</p>
            </div>
            <div class="campaign-compare-card campaign-compare-card--success">
              <div class="text-xs font-semibold text-emerald-700 mb-1">After</div>
              <p class="text-slate-700 whitespace-pre-wrap leading-6">{{ refinedCaption }}</p>
            </div>
          </div>

          <div v-if="versions.length" class="mt-4 space-y-2">
            <p class="text-xs font-semibold text-slate-500">Version history</p>
            <div v-for="v in versions" :key="v.id" class="campaign-version-row">
              <span class="font-medium text-slate-700">v{{ v.version }}</span>
              <span class="text-slate-500">{{ formatStatusLabel(v.status) }}</span>
            </div>
          </div>
        </template>

        <template #footer>
          <AppButton variant="secondary" @click="closeRefineModal">Close</AppButton>
          <AppButton :disabled="refining || !instruction.trim()" @click="refine">
            {{ refining ? 'Refining…' : 'Refine with AI' }}
          </AppButton>
        </template>
      </AppModal>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useCampaignsStore } from '../stores/campaigns';
import { useToastStore } from '../stores/toast';
import {
  AppAlert,
  AppButton,
  AppCard,
  AppEmptyState,
  AppFormField,
  AppInput,
  AppLoader,
  AppModal,
  AppSelect,
} from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';

const route = useRoute();
const router = useRouter();
const store = useCampaignsStore();
const toast = useToastStore();

const campaign = ref(null);
const loading = ref(true);
const loadError = ref(null);
const campaignError = ref('');
const generating = ref(false);
const savingCampaign = ref(false);

const activeTab = ref('brief');
const expandedPackageId = ref(null);
const platformFilter = ref('all');
const refineModalOpen = ref(false);

const selected = ref(null);
const selectedPackageId = ref(null);
const instruction = ref('');
const refining = ref(false);
const refinedCaption = ref('');
const versions = ref([]);
const assets = ref([]);
const assetPick = reactive({});
const manualUrl = reactive({});
const mediaMode = reactive({});

const campaignForm = reactive({
  name: '',
  description: '',
  product_info: '',
  tone: '',
  targetAudienceText: '',
  goalsText: '',
  platformsText: '',
});

const packages = computed(() => campaign.value?.content_packages || []);
const platformCount = computed(() => splitList(campaignForm.platformsText).length);
const approvedCount = computed(() => packages.value.filter((pkg) => pkg.status === 'approved').length);

const briefSummaryLine = computed(() => {
  const tone = campaignForm.tone?.trim() || 'No tone';
  const count = platformCount.value;
  const platformLabel = count === 1 ? '1 platform' : `${count} platforms`;
  return `${tone} · ${platformLabel}`;
});

const briefProductPreview = computed(() => {
  const text = campaignForm.product_info?.trim();
  return text ? text : null;
});

const pageSubtitle = computed(() => (
  activeTab.value === 'brief'
    ? 'Set up the campaign brief for AI generation.'
    : 'Review and publish platform drafts.'
));

const draftPlatformOptions = computed(() => {
  const platforms = [...new Set(packages.value.map((p) => p.platform))];
  return [
    { id: 'all', count: packages.value.length },
    ...platforms.map((p) => ({
      id: p,
      count: packages.value.filter((x) => x.platform === p).length,
    })),
  ];
});

const filteredPackages = computed(() => {
  if (platformFilter.value === 'all') return packages.value;
  return packages.value.filter((p) => p.platform === platformFilter.value);
});

const campaignPayload = computed(() => ({
  name: campaignForm.name.trim(),
  description: nullableString(campaignForm.description),
  product_info: nullableString(campaignForm.product_info),
  tone: nullableString(campaignForm.tone),
  target_audience: normalizedListValue(campaignForm.targetAudienceText),
  goals: normalizedListValue(campaignForm.goalsText),
  platforms: normalizedListValue(campaignForm.platformsText),
}));

const normalizedCampaignSnapshot = computed(() => {
  if (!campaign.value) return null;
  return JSON.stringify({
    name: campaign.value.name || '',
    description: campaign.value.description || null,
    product_info: campaign.value.product_info || null,
    tone: campaign.value.tone || null,
    target_audience: normalizeExistingList(campaign.value.target_audience),
    goals: normalizeExistingList(campaign.value.goals),
    platforms: normalizeExistingList(campaign.value.platforms),
  });
});

const normalizedFormSnapshot = computed(() => JSON.stringify(campaignPayload.value));
const canSaveCampaign = computed(
  () => Boolean(campaignForm.name.trim())
    && !savingCampaign.value
    && normalizedFormSnapshot.value !== normalizedCampaignSnapshot.value,
);

onMounted(async () => {
  await Promise.all([load(), loadAssets()]);
});

watch(
  () => packages.value.length,
  (len, prev) => {
    if (len > 0 && (prev === 0 || prev === undefined)) {
      activeTab.value = 'drafts';
    }
  },
);

function applyDefaultTab() {
  activeTab.value = packages.value.length > 0 ? 'drafts' : 'brief';
}

async function load(options = {}) {
  const { showLoader = true, preserveSelectedId = selectedPackageId.value } = options;
  if (showLoader) loading.value = true;
  loadError.value = null;
  try {
    campaign.value = await store.fetchOne(route.params.id);
    hydrateCampaignForm(campaign.value);
    if (showLoader) applyDefaultTab();
    restoreSelectedPackage(preserveSelectedId);
  } catch (e) {
    loadError.value = store.error || e.response?.data?.message || 'Failed to load campaign';
  } finally {
    if (showLoader) loading.value = false;
  }
}

function extractAssetList(responseData) {
  const payload = responseData?.data ?? responseData;
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

async function loadAssets() {
  try {
    const { data } = await axios.get('/api/content/assets', {
      params: { per_page: 100 },
      skipErrorToast: true,
    });
    assets.value = extractAssetList(data);
  } catch {
    assets.value = [];
  }
}

function hydrateCampaignForm(value) {
  campaignForm.name = value?.name || '';
  campaignForm.description = value?.description || '';
  campaignForm.product_info = value?.product_info || '';
  campaignForm.tone = value?.tone || '';
  campaignForm.targetAudienceText = joinList(value?.target_audience);
  campaignForm.goalsText = joinList(value?.goals);
  campaignForm.platformsText = joinList(value?.platforms, ', ');
}

function joinList(value, separator = '\n') {
  return Array.isArray(value) ? value.join(separator) : '';
}

function splitList(value) {
  return String(value || '')
    .split(/\r?\n|,/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function nullableString(value) {
  const trimmed = String(value || '').trim();
  return trimmed ? trimmed : null;
}

function normalizedListValue(value) {
  const list = splitList(value);
  return list.length ? list : null;
}

function normalizeExistingList(value) {
  return Array.isArray(value) && value.length ? value : null;
}

function restoreSelectedPackage(packageId) {
  if (!packageId) {
    selected.value = null;
    return;
  }
  const nextPackage = packages.value.find((pkg) => pkg.id === packageId);
  if (!nextPackage) {
    selected.value = null;
    selectedPackageId.value = null;
    instruction.value = '';
    refinedCaption.value = '';
    versions.value = [];
    return;
  }
  selected.value = nextPackage;
}

function packageKey(packageId) {
  return String(packageId);
}

function assetPickValue(packageId) {
  return assetPick[packageKey(packageId)] ?? '';
}

function setAssetPick(packageId, value) {
  assetPick[packageKey(packageId)] = value;
}

function manualUrlValue(packageId) {
  return manualUrl[packageKey(packageId)] ?? '';
}

function setManualUrl(packageId, value) {
  manualUrl[packageKey(packageId)] = value;
}

function ensurePackageMediaState(packageId) {
  const key = packageKey(packageId);
  if (!(key in assetPick)) assetPick[key] = '';
  if (!(key in manualUrl)) manualUrl[key] = '';
  if (!(key in mediaMode)) mediaMode[key] = 'library';
}

function toggleExpand(packageId) {
  if (expandedPackageId.value === packageId) {
    expandedPackageId.value = null;
    return;
  }
  expandedPackageId.value = packageId;
  ensurePackageMediaState(packageId);
}

function onPackageStatusChange(pkg, status) {
  pkg.status = status;
  updateStatus(pkg);
}

function mediaModeFor(packageId) {
  return mediaMode[packageKey(packageId)] || 'library';
}

function setMediaMode(packageId, mode) {
  mediaMode[packageKey(packageId)] = mode;
}

function openRefine(pkg) {
  selectedPackageId.value = pkg.id;
  selected.value = pkg;
  instruction.value = '';
  refinedCaption.value = '';
  loadVersions(pkg);
  refineModalOpen.value = true;
}

function closeRefineModal() {
  refineModalOpen.value = false;
}

function formatAssetTags(asset) {
  const tags = asset.ai_tags || [];
  if (!tags.length) return '';
  return ` · ${tags.slice(0, 3).join(', ')}`;
}

function formatStatusLabel(status) {
  const labels = {
    draft: 'Draft',
    in_review: 'In review',
    approved: 'Approved',
    rejected: 'Rejected',
    generated: 'Generated',
  };
  return labels[status] || 'Draft';
}

function assetsForPlatform(platform) {
  const list = assets.value;
  if (!list.length) return [];

  const needsVideo = platform === 'tiktok';
  const needsImage = ['instagram', 'twitter', 'threads', 'facebook', 'linkedin'].includes(platform);
  const filtered = list.filter((asset) => {
    if (needsVideo) return asset.type === 'video';
    if (needsImage) return asset.type === 'image' || !asset.type;
    return true;
  });

  return filtered.length ? filtered : list;
}

async function saveCampaign() {
  if (!campaign.value || !canSaveCampaign.value) return;
  savingCampaign.value = true;
  campaignError.value = '';
  try {
    await axios.patch(`/api/campaigns/${campaign.value.id}`, campaignPayload.value);
    toast.success('Campaign updated');
    await load({ showLoader: false });
  } catch (e) {
    campaignError.value = e.response?.data?.message || 'Failed to update campaign';
  } finally {
    savingCampaign.value = false;
  }
}

async function generate() {
  generating.value = true;
  try {
    await store.generate(route.params.id);
    await load({ showLoader: false });
    activeTab.value = 'drafts';
    platformFilter.value = 'all';
    if (packages.value.length === 1) {
      expandedPackageId.value = packages.value[0].id;
    }
  } finally {
    generating.value = false;
  }
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
  if (!selected.value || !instruction.value.trim()) return;
  refining.value = true;
  try {
    const { data } = await axios.post(`/api/content-packages/${selected.value.id}/refine`, {
      instruction: instruction.value.trim(),
    });
    const refined = data.data || data;
    refinedCaption.value = refined.caption;
    toast.success('Caption refined');
    await load({ showLoader: false, preserveSelectedId: selected.value.id });
    expandedPackageId.value = selected.value.id;
  } finally {
    refining.value = false;
  }
}

async function updateStatus(pkg) {
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/status`, { status: pkg.status });
    toast.success('Status updated');
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

async function attachAsset(pkg) {
  const assetId = assetPickValue(pkg.id);
  if (!assetId) return;
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/media`, {
      asset_ids: [Number(assetId)],
    });
    toast.success('Asset attached');
    setAssetPick(pkg.id, '');
    await load({ showLoader: false, preserveSelectedId: pkg.id });
    expandedPackageId.value = pkg.id;
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

async function addManualUrl(pkg) {
  const url = manualUrlValue(pkg.id).trim();
  if (!url) return;
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/media`, {
      media_urls: [url],
    });
    toast.success('Media URL added');
    setManualUrl(pkg.id, '');
    await load({ showLoader: false, preserveSelectedId: pkg.id });
    expandedPackageId.value = pkg.id;
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

function schedulePackage(pkg) {
  router.push({ path: '/calendar', query: { content_package_id: pkg.id } });
}
</script>

<style scoped>
.campaign-mode-tabs {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.25rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #f8fafc;
}

.campaign-mode-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.82rem;
  font-weight: 500;
  color: #64748b;
  transition: background 0.15s ease, color 0.15s ease;
}

.campaign-mode-tab:hover {
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
}

.campaign-mode-tab--active {
  background: #fff;
  color: #1e3a8a;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.campaign-mode-tab__badge {
  font-size: 0.68rem;
  line-height: 1;
  padding: 0.15rem 0.4rem;
  border-radius: 999px;
  background: rgba(30, 58, 138, 0.1);
  color: #1d4ed8;
}

.campaign-brief-card {
  border: 1px solid #e6ebf2;
  background: #fff;
}

.campaign-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}

.campaign-brief-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: rgba(248, 250, 252, 0.9);
}

.campaign-platform-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.7rem;
  border: 1px solid #e6ebf2;
  border-radius: 999px;
  background: #fff;
  transition: all 0.15s ease;
}

.campaign-platform-chip:hover {
  border-color: rgba(30, 58, 138, 0.25);
  color: #334155;
}

.campaign-platform-chip--active {
  border-color: rgba(30, 58, 138, 0.35);
  background: rgba(239, 246, 255, 0.95);
  color: #1d4ed8;
}

.campaign-draft-card {
  border: 1px solid #e6ebf2;
  background: #fff;
  overflow: hidden;
}

.campaign-draft-card--expanded {
  border-color: rgba(30, 58, 138, 0.22);
  box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.06);
}

.campaign-draft-row {
  display: grid;
  gap: 0.75rem;
  padding: 0.75rem 0.85rem;
}

@media (min-width: 768px) {
  .campaign-draft-row {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}

.campaign-draft-row__main {
  display: grid;
  gap: 0.35rem;
  min-width: 0;
  text-align: left;
  width: 100%;
}

.campaign-draft-row__main:hover {
  cursor: pointer;
}

.campaign-draft-preview {
  font-size: 0.82rem;
  line-height: 1.45;
  color: #475569;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.campaign-draft-status-badge {
  font-size: 0.68rem;
  padding: 0.12rem 0.4rem;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
}

.campaign-draft-row__side {
  display: grid;
  gap: 0.5rem;
  min-width: 7.5rem;
}

.campaign-draft-expanded {
  display: grid;
  gap: 0.75rem;
  padding: 0 0.85rem 0.85rem;
  border-top: 1px solid #eef2f6;
  background: rgba(248, 250, 252, 0.45);
}

.campaign-media-list {
  display: grid;
  gap: 0.35rem;
  padding: 0.65rem 0.75rem;
  border: 1px dashed #cbd5e1;
  border-radius: 0.75rem;
  background: #fff;
}

.campaign-media-block {
  display: grid;
  gap: 0.65rem;
}

.campaign-media-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.2rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.55rem;
  background: #f8fafc;
  width: fit-content;
}

.campaign-media-toggle__btn {
  padding: 0.3rem 0.65rem;
  border-radius: 0.4rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.campaign-media-toggle__btn--active {
  background: #fff;
  color: #1d4ed8;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.campaign-compare-card {
  padding: 0.75rem 0.85rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.875rem;
  background: #f8fafc;
}

.campaign-compare-card--success {
  border-color: rgba(16, 185, 129, 0.22);
  background: rgba(236, 253, 245, 0.85);
}

.campaign-version-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #f8fafc;
}
</style>
