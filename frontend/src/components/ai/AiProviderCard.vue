<script setup>
import { computed } from 'vue';
import AiProviderBadge from './AiProviderBadge.vue';
import AiProviderIcon from './AiProviderIcon.vue';
import { getAiProviderMeta } from '../../constants/aiProviders';
import { AppButton, AppFormField, AppIcon, AppInput, AppTitle, AppBadge } from '../ui';

const props = defineProps({
  provider: { type: Object, required: true },
  isDefault: { type: Boolean, default: false },
  apiKeyUrl: { type: String, default: '' },
  statusText: { type: String, required: true },
  showKeyForm: { type: Boolean, default: true },
  noKeyMessage: { type: String, default: '' },
  keyValue: { type: String, default: '' },
  keyVisible: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  fieldId: { type: String, required: true },
});

const emit = defineEmits(['update:keyValue', 'update:keyVisible', 'save-key', 'remove-key']);

const meta = computed(() => getAiProviderMeta(props.provider.id));
const hasDraft = computed(() => Boolean(props.keyValue.trim()));
</script>

<template>
  <article class="ai-provider-card">
    <div class="ai-provider-card__wash" aria-hidden="true" />
    <AiProviderIcon
      :id="meta.icon"
      :letter="meta.letter"
      class="ai-provider-card__watermark"
      aria-hidden="true"
    />

    <div class="ai-provider-card__row">
      <div class="ai-provider-card__info">
        <AiProviderBadge :id="provider.id" />
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <AppTitle size="sm">{{ provider.label }}</AppTitle>
            <AppBadge v-if="isDefault" variant="purple">Default</AppBadge>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">{{ statusText }}</p>
          <a
            v-if="apiKeyUrl"
            :href="apiKeyUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="ai-provider-card__link"
          >
            Get an API key
            <AppIcon name="link" class="w-3.5 h-3.5" />
          </a>
        </div>
      </div>

      <div class="ai-provider-card__status">
        <span
          class="ai-provider-status-dot"
          :class="provider.available ? 'ai-provider-status-dot--on' : 'ai-provider-status-dot--off'"
        />
        <span :class="provider.available ? 'text-emerald-700' : 'text-amber-700'">
          {{ provider.available ? 'Ready' : 'Not configured' }}
        </span>
      </div>

      <div class="ai-provider-card__key">
        <template v-if="showKeyForm">
          <div v-if="provider.byok.configured" class="flex items-center justify-between gap-3 text-sm mb-2">
            <span class="text-slate-600">Your key ends in •••• {{ provider.byok.last_four }}</span>
            <AppButton
              size="sm"
              variant="ghost"
              tone="destructive"
              :loading="saving"
              @click="emit('remove-key')"
            >
              Remove key
            </AppButton>
          </div>

          <AppFormField
            :id="fieldId"
            :label="provider.byok.configured ? 'Replace your API key' : 'Your API key'"
            hint="Stored encrypted. It is never shown again after saving."
          >
            <div class="relative">
              <AppInput
                :id="fieldId"
                :model-value="keyValue"
                :type="keyVisible ? 'text' : 'password'"
                autocomplete="off"
                placeholder="Paste your API key"
                input-class="!pr-9"
                @update:model-value="emit('update:keyValue', $event)"
                @keyup.enter="emit('save-key')"
              />
              <AppButton
                variant="ghost"
                size="sm"
                class="!absolute !inset-y-0 !right-0 !px-2.5 !py-0 !text-slate-400 hover:!text-slate-600"
                :title="keyVisible ? 'Hide API key' : 'Show API key'"
                @click="emit('update:keyVisible', !keyVisible)"
              >
                <AppIcon name="view" class="w-4 h-4" />
              </AppButton>
            </div>
          </AppFormField>

          <div class="flex justify-end mt-2">
            <AppButton
              size="sm"
              variant="secondary"
              :disabled="!hasDraft"
              :loading="saving"
              @click="emit('save-key')"
            >
              {{ provider.byok.configured ? 'Replace key' : 'Save' }}
            </AppButton>
          </div>
        </template>
        <p v-else class="text-sm text-slate-500">{{ noKeyMessage }}</p>
      </div>
    </div>
  </article>
</template>

<style scoped>
.ai-provider-card {
  position: relative;
  container-type: inline-size;
  isolation: isolate;
  overflow: hidden;
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  padding: 1.25rem;
}

.ai-provider-card__wash {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    radial-gradient(circle at 100% 0%, rgba(59, 130, 246, 0.1), transparent 42%),
    radial-gradient(circle at 92% 22%, rgba(236, 72, 153, 0.08), transparent 38%),
    radial-gradient(circle at 100% 45%, rgba(16, 185, 129, 0.08), transparent 32%);
}

.ai-provider-card__watermark {
  position: absolute;
  right: -1.5rem;
  top: -1.75rem;
  width: 9rem;
  height: 9rem;
  color: #0f172a;
  opacity: 0.06;
  transform: rotate(6deg);
  pointer-events: none;
  z-index: 0;
}

.ai-provider-card__row {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.ai-provider-card__info {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
}

.ai-provider-card__link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.35rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #2563eb;
  text-decoration: none;
}

.ai-provider-card__link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.ai-provider-card__status {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8125rem;
  font-weight: 600;
}

.ai-provider-status-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 999px;
  flex-shrink: 0;
}

.ai-provider-status-dot--on {
  background: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.16);
}

.ai-provider-status-dot--off {
  background: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.16);
}

.ai-provider-card__key {
  min-width: 0;
}

/* Once the card itself has room — whether it's alone in a full-width row or
   one of several columns on a very wide screen — lay info/status/key out
   side by side, matching the reference design. Narrower cards (e.g. 3-up on
   a medium screen) fall back to the stacked layout above, which stays fully
   usable rather than squeezing the API key field unreadably thin. */
@container (min-width: 640px) {
  .ai-provider-card__row {
    flex-direction: row;
    align-items: flex-start;
  }

  .ai-provider-card__info {
    flex: 1.3 1 0;
  }

  .ai-provider-card__status {
    flex: 0 0 auto;
    margin-top: 0.2rem;
  }

  .ai-provider-card__key {
    flex: 1.4 1 0;
  }
}
</style>
