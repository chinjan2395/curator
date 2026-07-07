import { useNavigationSettingsStore } from '../stores/navigationSettings';

export function useNavigationVisibility() {
  const navigation = useNavigationSettingsStore();
  return {
    isMenuEnabled: (id) => navigation.isMenuEnabled(id),
    isFeatureEnabled: (id) => navigation.isFeatureEnabled(id),
  };
}
