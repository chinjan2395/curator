import { computed, onMounted } from 'vue';
import { useCapabilities } from './useCapabilities';
import { normalizePlatformKey } from '../constants/platformPublishSpecs';

export function usePlatformPublishSpecs() {
  const { capabilities, loading, error, fetchCapabilities } = useCapabilities();

  onMounted(() => {
    fetchCapabilities();
  });

  const specs = computed(() => capabilities.value?.publish?.content_specs ?? {});

  function getSpec(platform) {
    const key = normalizePlatformKey(platform);
    return specs.value[key] ?? null;
  }

  function getSpecsForPlatforms(platforms) {
    const keys = [...new Set((platforms || []).map(normalizePlatformKey).filter(Boolean))];
    return keys
      .map((key) => ({ platform: key, spec: specs.value[key] }))
      .filter((entry) => entry.spec);
  }

  function supportedTypeLabels(platform) {
    const spec = getSpec(platform);
    if (!spec?.content_types) return [];
    return spec.content_types
      .filter((t) => t.supported)
      .map((t) => t.label);
  }

  function isNativePublishPlatform(platform) {
    const spec = getSpec(platform);
    return Boolean(spec?.native_publish);
  }

  return {
    specs,
    loading,
    error,
    fetchCapabilities,
    getSpec,
    getSpecsForPlatforms,
    supportedTypeLabels,
    isNativePublishPlatform,
  };
}
