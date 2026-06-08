<template>
  <div class="space-y-4">
    <AppPageHeader
      title="New campaign"
      subtitle="Define your briefing for AI content generation."
      icon="megaphone"
      :breadcrumb="['Campaigns', 'New campaign']"
    >
      <template #actions>
        <AppButton variant="secondary" to="/campaigns">Cancel</AppButton>
        <AppButton
          variant="primary"
          type="submit"
          form="campaign-form"
          :disabled="saving || !form.name.trim()"
        >
          {{ saving ? 'Creating…' : 'Create campaign' }}
        </AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="ai" />

    <AppCard padding="none" class="campaign-brief-card p-4">
      <form id="campaign-form" class="space-y-3" @submit.prevent="submit">
        <div class="campaign-form-panel">
          <p class="text-sm-pro font-semibold text-slate-800">Basics</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
            <AppFormField label="Campaign name" required>
              <AppInput v-model="form.name" type="text" placeholder="Summer product launch" required />
            </AppFormField>
            <AppFormField label="Tone">
              <AppInput v-model="form.tone" type="text" placeholder="Professional" />
            </AppFormField>
          </div>
          <AppFormField label="Platforms" hint="Comma-separated: instagram, linkedin, tiktok" class="mt-3">
            <AppInput v-model="platformsText" type="text" placeholder="instagram, twitter, facebook" />
          </AppFormField>
        </div>

        <div class="campaign-form-panel">
          <p class="text-sm-pro font-semibold text-slate-800">Message</p>
          <div class="grid grid-cols-1 gap-3 mt-3">
            <AppFormField label="Product / service">
              <AppInput
                v-model="form.product_info"
                type="textarea"
                :rows="4"
                placeholder="What are you promoting?"
              />
            </AppFormField>
            <AppFormField label="Description (optional)">
              <AppInput
                v-model="form.description"
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
              <AppInput v-model="targetAudienceText" type="textarea" :rows="4" />
            </AppFormField>
            <AppFormField label="Goals" hint="One per line or comma-separated">
              <AppInput v-model="goalsText" type="textarea" :rows="4" />
            </AppFormField>
          </div>
        </div>
      </form>
    </AppCard>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCampaignsStore } from '../stores/campaigns';
import { AppButton, AppCard, AppFormField, AppInput } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';

const router = useRouter();
const store = useCampaignsStore();
const platformsText = ref('instagram,twitter,facebook');
const targetAudienceText = ref('');
const goalsText = ref('');
const saving = ref(false);
const form = reactive({
  name: '',
  product_info: '',
  tone: 'professional',
  description: '',
});

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

async function submit() {
  if (!form.name.trim()) return;

  saving.value = true;
  try {
    const campaign = await store.create({
      name: form.name.trim(),
      description: nullableString(form.description),
      product_info: nullableString(form.product_info),
      tone: nullableString(form.tone),
      target_audience: normalizedListValue(targetAudienceText.value),
      goals: normalizedListValue(goalsText.value),
      platforms: normalizedListValue(platformsText.value),
    });
    router.push(`/campaigns/${campaign.id}`);
  } catch {
    // toast handled by store / interceptor
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
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
</style>
