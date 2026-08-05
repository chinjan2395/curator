<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { absolutePageAssetUrl, apiUrlFromAny } from '../../config/api.js';

/**
 * Renders the real embed runtime against an unsaved settings draft.
 *
 * The preview used to be a second, hand-maintained Vue implementation of the
 * card markup, which drifted from `curator-embed.js` (click actions, stagger
 * animation, layers width) and made settings look like they did nothing. This
 * boots the shipped runtime inside a `srcdoc` iframe and pushes the draft in
 * over `postMessage`, so preview and production are the same code path.
 */
const props = defineProps({
  publicKey: { type: String, required: true },
  /** PublishSettings-shaped tree. Pushed to the runtime on every change. */
  settings: { type: Object, default: null },
  /** Resolved widget theme ('light' | 'dark') — drives the page behind the widget. */
  theme: { type: String, default: 'light' },
  /** Cache-busting suffix; bump to force a fresh runtime + CSS fetch. */
  version: { type: [String, Number], default: 0 },
  cssUrl: { type: String, default: '' },
  jsUrl: { type: String, default: '' },
  minHeight: { type: Number, default: 320 },
});

const iframeRef = ref(null);
const height = ref(props.minHeight);

/** `srcdoc` inherits this document's origin, so neither side needs a wildcard. */
const origin = typeof window !== 'undefined' ? window.location.origin : '';

function withVersion(url) {
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}pv=${props.version}`;
}

const cssHref = computed(() => {
  const key = props.publicKey;
  if (!key) return '';
  const base = props.cssUrl || `/api/embed/${encodeURIComponent(key)}.css`;
  return absolutePageAssetUrl(withVersion(apiUrlFromAny(base)));
});

const jsHref = computed(() => {
  const key = props.publicKey;
  if (!key) return '';
  const base = props.jsUrl || `/api/embed/${encodeURIComponent(key)}.js`;
  return absolutePageAssetUrl(withVersion(apiUrlFromAny(base)));
});

const pageBackground = computed(() => (props.theme === 'dark' ? '#0b1220' : '#f8fafc'));

const srcdoc = computed(() => {
  const key = props.publicKey;
  if (!key) return '';

  // Declaring CRT_PREVIEW before the runtime loads is what opts this document
  // into accepting a pushed draft; production snippets never define it.
  const bridge = `<scr` + `ipt>var CRT_PREVIEW = ${JSON.stringify(origin)};</scr` + `ipt>`;
  const css = `<link rel="stylesheet" href="${cssHref.value}">`;
  const js = `<scr` + `ipt src="${jsHref.value}"></scr` + `ipt>`;
  const baseCss = `<style>
body{margin:0;padding:16px;background:${pageBackground.value};overflow:visible}
[data-curator-feed]{display:block}
</style>`;

  const heightScript = `<scr` + `ipt>
(function(){
  var KEY = ${JSON.stringify(String(key))};
  var ORIGIN = ${JSON.stringify(origin)};
  var last = 0;
  var pending = false;
  function measure(){
    // Measure the feed container, not the document: body scrollHeight tracks
    // the iframe viewport and would feed back into the height we just set.
    var pad = 32;
    var feed = document.querySelector('[data-curator-feed]');
    if (feed) {
      var rect = feed.getBoundingClientRect();
      var h = Math.max(rect && rect.height ? rect.height : 0, feed.scrollHeight || 0);
      return Math.max(0, Math.ceil(h + pad));
    }
    var b = document.body, d = document.documentElement;
    return Math.max(0, Math.ceil(Math.max(b ? b.scrollHeight : 0, d ? d.scrollHeight : 0)));
  }
  function send(){
    pending = false;
    var h = measure();
    if (!h || h === last) return;
    last = h;
    try { parent.postMessage({ type: 'curator:embedHeight', key: KEY, height: h }, ORIGIN); } catch (e) {}
  }
  function schedule(){
    if (pending) return;
    pending = true;
    requestAnimationFrame(send);
  }
  window.addEventListener('load', function(){ setTimeout(schedule, 0); setTimeout(schedule, 200); setTimeout(schedule, 800); });
  window.addEventListener('resize', schedule);
  try {
    if ('ResizeObserver' in window) {
      var ro = new ResizeObserver(schedule);
      if (document.body) ro.observe(document.body);
      if (document.documentElement) ro.observe(document.documentElement);
    } else {
      new MutationObserver(schedule).observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
    }
  } catch (e) {}
  schedule();
})();
</scr` + `ipt>`;

  return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${baseCss}${css}</head><body>${bridge}<div data-curator-feed="${key}"></div>${js}${heightScript}</body></html>`;
});

function pushDraft() {
  const win = iframeRef.value?.contentWindow;
  if (!win || !props.settings || !props.publicKey) return;
  try {
    win.postMessage(
      {
        type: 'curator:settings',
        key: String(props.publicKey),
        settings: JSON.parse(JSON.stringify(props.settings)),
      },
      origin,
    );
  } catch {
    /* preview only — a failed handoff just leaves the last rendered state */
  }
}

let pushTimer = null;
function schedulePush() {
  if (pushTimer) clearTimeout(pushTimer);
  // Debounced so dragging a colour picker does not re-boot the feed per frame.
  pushTimer = setTimeout(() => {
    pushTimer = null;
    pushDraft();
  }, 180);
}

function onMessage(event) {
  if (event.origin !== origin) return;
  const win = iframeRef.value?.contentWindow;
  if (!win || event.source !== win) return;
  const data = event.data || {};
  if (String(data.key || '') !== String(props.publicKey || '')) return;

  if (data.type === 'curator:ready') {
    pushDraft();
    return;
  }
  if (data.type !== 'curator:embedHeight') return;
  const h = Number(data.height);
  if (!Number.isFinite(h)) return;
  height.value = Math.max(props.minHeight, Math.ceil(h));
}

watch(() => props.settings, schedulePush, { deep: true });

onMounted(() => window.addEventListener('message', onMessage));
onBeforeUnmount(() => {
  window.removeEventListener('message', onMessage);
  if (pushTimer) clearTimeout(pushTimer);
});
</script>

<template>
  <iframe
    v-if="publicKey"
    ref="iframeRef"
    :srcdoc="srcdoc"
    class="w-full border-0 block"
    title="Embed preview"
    sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
    scrolling="no"
    :style="{ height: `${height}px` }"
  />
</template>
