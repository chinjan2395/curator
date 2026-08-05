<script setup>
import { AppCard, AppSegmentedControl } from '../ui';

/**
 * Browser-framed preview canvas with a viewport switcher.
 *
 * The preview used to render at whatever width the page column happened to be,
 * which is never the width of the host site — and most embed traffic is mobile.
 * Constraining the stage lets column collapse be checked before publishing
 * rather than after. The frame is presentation only; the feed markup itself is
 * passed through the default slot so the embed stylesheet still owns it.
 */
const DEVICES = [
  { value: 'desktop', label: 'Desktop', icon: 'view' },
  { value: 'tablet', label: 'Tablet', icon: 'image' },
  { value: 'mobile', label: 'Mobile', icon: 'flag' },
];

defineProps({
  modelValue: { type: String, default: 'desktop' },
  styleLabel: { type: String, default: '' },
  jsonUrl: { type: String, default: '' },
  siteLabel: { type: String, default: 'your-site.com' },
  /**
   * Resolved widget theme. The stage has to follow it — dark cards on a white
   * sheet was the single biggest reason the theme setting looked broken.
   */
  theme: { type: String, default: 'light' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <AppCard padding="none" class="overflow-hidden">
    <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-slate-200">
      <div class="min-w-0">
        <div class="text-sm-pro font-semibold text-slate-800">Preview</div>
        <div class="text-2xs text-slate-400 truncate">
          <template v-if="styleLabel">{{ styleLabel }} · </template>same CSS your embed loads
        </div>
      </div>

      <div class="ml-auto flex items-center gap-2.5">
        <AppSegmentedControl
          :model-value="modelValue"
          :options="DEVICES"
          aria-label="Preview width"
          compact
          @update:model-value="$emit('update:modelValue', $event)"
        />

        <a
          v-if="jsonUrl"
          :href="jsonUrl"
          target="_blank"
          rel="noreferrer"
          class="text-2xs text-slate-500 hover:text-slate-700 underline underline-offset-2 shrink-0"
        >
          Open JSON
        </a>
      </div>
    </div>

    <div class="publish-preview-stage" :data-theme="theme">
      <div class="publish-preview-device" :data-width="modelValue" :data-theme="theme">
        <div class="publish-preview-chrome">
          <span class="publish-preview-lights"><i /><i /><i /></span>
          <span class="publish-preview-url">{{ siteLabel }}</span>
        </div>
        <div class="publish-preview-body">
          <slot />
        </div>
      </div>
    </div>
  </AppCard>
</template>

<style scoped>
.publish-preview-stage {
  display: flex;
  justify-content: center;
  padding: 1rem;
  background:
    radial-gradient(circle at 1px 1px, #dde4ee 1px, transparent 0) 0 0 / 14px 14px,
    #f8fafc;
}

.publish-preview-stage[data-theme='dark'] {
  background:
    radial-gradient(circle at 1px 1px, #1e293b 1px, transparent 0) 0 0 / 14px 14px,
    #020617;
}

.publish-preview-body {
  padding: 0.75rem;
}

/* The iframe brings its own body padding, so a nested gutter would double it. */
.publish-preview-body:has(> iframe) {
  padding: 0;
}

.publish-preview-device {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
  transition: max-width 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

.publish-preview-device[data-theme='dark'] {
  border-color: #1e293b;
  background: #0b1220;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.5);
}

.publish-preview-device[data-theme='dark'] .publish-preview-chrome {
  border-bottom-color: #1e293b;
  background: #0f172a;
}

.publish-preview-device[data-theme='dark'] .publish-preview-lights i {
  background: #334155;
}

.publish-preview-device[data-width='tablet'] {
  max-width: 620px;
}

.publish-preview-device[data-width='mobile'] {
  max-width: 340px;
}

.publish-preview-chrome {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  background: #fcfdfe;
}

.publish-preview-lights {
  display: flex;
  gap: 0.25rem;
}

.publish-preview-lights i {
  display: block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 9999px;
  background: #e2e8f0;
}

.publish-preview-url {
  flex: 1;
  text-align: center;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.6875rem;
  color: #94a3b8;
}

@media (prefers-reduced-motion: reduce) {
  .publish-preview-device {
    transition: none;
  }
}
</style>
