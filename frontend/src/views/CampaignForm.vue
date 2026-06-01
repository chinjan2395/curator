<template>
  <div class="space-y-4 max-w-2xl">
    <AppPageHeader title="New campaign" subtitle="Define your briefing for AI content generation." icon="megaphone" />
    <CapabilityBanner context="ai" />
    <AppCard class="p-4 space-y-3">
      <AppFormField label="Name"><AppInput v-model="form.name" /></AppFormField>
      <AppFormField label="Product / service"><AppInput v-model="form.product_info" type="textarea" /></AppFormField>
      <AppFormField label="Tone"><AppInput v-model="form.tone" placeholder="professional" /></AppFormField>
      <AppFormField label="Platforms (comma-separated)"><AppInput v-model="platformsText" /></AppFormField>
      <AppButton variant="primary" :disabled="saving" @click="submit">{{ saving ? 'Creating…' : 'Create campaign' }}</AppButton>
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
const saving = ref(false);
const form = reactive({ name: '', product_info: '', tone: 'professional', description: '' });

async function submit() {
  saving.value = true;
  try {
    const campaign = await store.create({
      ...form,
      platforms: platformsText.value.split(',').map((p) => p.trim()).filter(Boolean),
    });
    router.push(`/campaigns/${campaign.id}`);
  } catch {
    // toast handled by store / interceptor
  } finally {
    saving.value = false;
  }
}
</script>
