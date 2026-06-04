/** Shared platform metadata for icons, labels, and brand colors across the app. */

export const SOCIAL_PLATFORMS = {
  youtube: {
    label: 'YouTube',
    color: '#ef4444',
    softBg: 'rgba(239, 68, 68, 0.12)',
    tagline: 'Channel connect',
  },
  google: {
    label: 'Google',
    color: '#2563eb',
    softBg: 'rgba(37, 99, 235, 0.12)',
    tagline: 'Google account',
  },
  facebook: {
    label: 'Facebook',
    color: '#1877f2',
    softBg: 'rgba(24, 119, 242, 0.14)',
    tagline: 'Pages access',
  },
  instagram: {
    label: 'Instagram',
    color: '#db2777',
    softBg: 'rgba(219, 39, 119, 0.14)',
    tagline: 'Meta business',
  },
  twitter: {
    label: 'Twitter / X',
    color: '#111827',
    softBg: 'rgba(17, 24, 39, 0.10)',
    tagline: 'API tokens',
  },
  tiktok: {
    label: 'TikTok',
    color: '#111827',
    softBg: 'rgba(17, 24, 39, 0.10)',
    tagline: 'Creator access',
  },
  threads: {
    label: 'Threads',
    color: '#111827',
    softBg: 'rgba(17, 24, 39, 0.10)',
    tagline: 'Meta Threads',
  },
  linkedin: {
    label: 'LinkedIn',
    color: '#0a66c2',
    softBg: 'rgba(10, 102, 194, 0.12)',
    tagline: 'Share on LinkedIn',
  },
  rss: {
    label: 'RSS / Atom',
    color: '#ea580c',
    softBg: 'rgba(234, 88, 12, 0.12)',
    tagline: 'Feed URL',
  },
  other: {
    label: 'Other',
    color: '#475569',
    softBg: 'rgba(71, 85, 105, 0.12)',
    tagline: 'Custom provider',
  },
};

const ALIASES = {
  x: 'twitter',
};

/** @returns {string} Icon key used by SocialIcon.vue */
export function normalizePlatformType(type) {
  const raw = String(type || 'other').toLowerCase().trim();
  const mapped = ALIASES[raw] ?? raw;
  return SOCIAL_PLATFORMS[mapped] ? mapped : 'other';
}

/** @returns {string} Human-readable platform name */
export function getPlatformLabel(type) {
  const key = normalizePlatformType(type);
  return SOCIAL_PLATFORMS[key]?.label ?? String(type || 'Other').replace(/_/g, ' ');
}

/** @returns {{ label: string, color: string, softBg: string, tagline?: string }} */
export function getPlatformMeta(type) {
  const key = normalizePlatformType(type);
  return SOCIAL_PLATFORMS[key] ?? SOCIAL_PLATFORMS.other;
}
