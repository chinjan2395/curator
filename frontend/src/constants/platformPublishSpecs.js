import { SOCIAL_PLATFORMS } from './socialPlatforms';

const ALIASES = {
  x: 'twitter',
};

export function normalizePlatformKey(platform) {
  const key = String(platform || '').trim().toLowerCase();
  return ALIASES[key] ?? key;
}

export function getPlatformDisplayLabel(platform) {
  const key = normalizePlatformKey(platform);
  return SOCIAL_PLATFORMS[key]?.label ?? key;
}

/** Content-type chip labels for compact UI */
export const CONTENT_TYPE_ICONS = {
  text: 'Aa',
  image: 'IMG',
  video: 'VID',
  carousel: 'CAR',
  article: 'LINK',
};
