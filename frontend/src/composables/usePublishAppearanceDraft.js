import { computed } from 'vue';

/**
 * Unsaved-change tracking for the Publish page's appearance form.
 *
 * `Publish.vue` edits a local draft (`appearance`) and only PUTs it on an
 * explicit save, so "what is different from what the server has" is the state
 * the save bar and the per-tab dots are driven from. Both the draft and the
 * baseline are the *merged* shape (`mergePublishAppearance`), so defaults that
 * the API omits never register as a change.
 *
 * Diffing lives here rather than in the view because pages must not contain
 * transformation logic.
 */

/** Section keys mirror the appearance tab strip in `Publish.vue`. */
const SECTION_BY_ROOT_KEY = {
  feed_style: 'layout',
  feed: 'layout',
  post: 'posts',
  colors: 'colors',
  widget: 'widget',
  branding: 'branding',
};

function isPlainObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Flattens a settings tree to `{ 'widget.gap': 16 }`. Arrays are compared as a
 * whole (platform/content-type filters are order-insensitive sets to the user,
 * so a serialized sorted copy avoids false positives on reorder).
 */
function flatten(value, prefix = '', out = {}) {
  if (Array.isArray(value)) {
    out[prefix] = JSON.stringify([...value].map(String).sort());
    return out;
  }
  if (isPlainObject(value)) {
    Object.keys(value).forEach((key) => {
      flatten(value[key], prefix ? `${prefix}.${key}` : key, out);
    });
    return out;
  }
  out[prefix] = value;
  return out;
}

/** Loose equality so `"16"` from a text input matches a saved numeric `16`. */
function sameLeaf(a, b) {
  if (a === b) return true;
  if (a === null || a === undefined || b === null || b === undefined) {
    return (a ?? null) === (b ?? null);
  }
  if (typeof a === 'boolean' || typeof b === 'boolean') return Boolean(a) === Boolean(b);
  return String(a) === String(b);
}

export function diffAppearancePaths(baseline, draft) {
  if (!baseline || !draft) return [];
  const before = flatten(baseline);
  const after = flatten(draft);
  const paths = new Set([...Object.keys(before), ...Object.keys(after)]);
  const changed = [];
  paths.forEach((path) => {
    if (!sameLeaf(before[path], after[path])) changed.push(path);
  });
  return changed.sort();
}

export function sectionForAppearancePath(path) {
  return SECTION_BY_ROOT_KEY[String(path).split('.')[0]] ?? 'layout';
}

/**
 * @param {import('vue').Ref} draftRef    live, user-edited appearance
 * @param {import('vue').Ref} baselineRef last-saved appearance (deep clone)
 */
export function usePublishAppearanceDraft(draftRef, baselineRef) {
  const dirtyPaths = computed(() => diffAppearancePaths(baselineRef.value, draftRef.value));

  const dirtyCount = computed(() => dirtyPaths.value.length);

  const isDirty = computed(() => dirtyCount.value > 0);

  const dirtySections = computed(() => {
    const sections = new Set();
    dirtyPaths.value.forEach((path) => sections.add(sectionForAppearancePath(path)));
    return sections;
  });

  return { dirtyPaths, dirtyCount, isDirty, dirtySections };
}
