import { computed, inject, provide } from 'vue';
import { EMBED_CAPABILITIES } from '../constants/embedCapabilities';

/**
 * Lets the Publish appearance panels double as the Brand Kit editor.
 *
 * Publish edits one flat settings tree; a child brand kit edits a *sparse*
 * override tree on top of its Master. Rather than fork the panels, the brand
 * kit editor provides this context and `AppearanceGroup` surfaces the
 * inherited/overridden state per group. `Publish.vue` provides nothing, so the
 * panels behave exactly as before there.
 */
const KEY = Symbol('appearance-overrides');

/**
 * @param {object} source
 * @param {import('vue').Ref<Set<string>>} source.overriddenPaths dot-paths, in panel space
 * @param {(path: string) => unknown} source.reset
 * @param {import('vue').Ref<boolean>} source.enabled false for a Master kit (nothing to inherit)
 */
export function provideAppearanceOverrides(source) {
  provide(KEY, source);
}

export function useAppearanceOverrides(tab, group) {
  const ctx = inject(KEY, null);

  const overrides = computed(() => {
    if (!ctx || !ctx.enabled.value) return [];
    return Object.entries(EMBED_CAPABILITIES)
      .filter(([path, def]) => def.tab === tab && def.group === group && ctx.overriddenPaths.value.has(path))
      .map(([path, def]) => ({ path, label: def.label || path }));
  });

  return {
    active: computed(() => !!ctx && ctx.enabled.value),
    overrides,
    reset: (path) => ctx?.reset(path),
  };
}

/** Dot-paths that belong to one panel group — used to scope "reset section". */
export function pathsInGroup(tab, group) {
  return Object.entries(EMBED_CAPABILITIES)
    .filter(([, def]) => def.tab === tab && def.group === group)
    .map(([path]) => path);
}
