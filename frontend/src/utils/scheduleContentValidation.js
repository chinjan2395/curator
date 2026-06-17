export function mediaUrls(pkg) {
  const urls = pkg?.media_urls;
  return Array.isArray(urls) ? urls.filter((u) => typeof u === 'string' && u.trim() !== '') : [];
}

function isHttpsUrl(url) {
  return /^https:\/\//i.test(String(url || ''));
}

const VIDEO_EXT = /\.(mp4|mov|avi|webm|mkv|m4v|wmv)(\?.*)?$/i;

function isVideoUrl(url) {
  return VIDEO_EXT.test(String(url || '').split('?')[0]);
}

function normalizePlatform(platform) {
  const key = String(platform || '').trim().toLowerCase();
  return key === 'x' ? 'twitter' : key;
}

const NATIVE_PROVIDERS = ['twitter', 'facebook', 'instagram', 'tiktok', 'threads', 'linkedin'];

/**
 * @returns {{ valid: boolean, checks: Array<{ id: string, label: string, passed: boolean, message: string|null }> }}
 */
export function validateScheduleContent(pkg, provider) {
  if (!pkg || !provider) {
    return { valid: false, checks: [] };
  }

  const platform = normalizePlatform(provider);
  const checks = [];

  const caption = String(pkg.caption || '').trim();
  checks.push(check(
    'caption',
    'Caption text',
    caption !== '',
    'Add caption text to the content package before scheduling.',
  ));

  const packagePlatform = normalizePlatform(pkg.platform);
  checks.push(check(
    'platform_match',
    'Platform matches account',
    packagePlatform === platform,
    `This package is for ${packagePlatform} but you selected ${platform}. Pick a matching package or account.`,
  ));

  const urls = mediaUrls(pkg);
  const hasMedia = urls.length > 0;

  if (platform === 'instagram' || platform === 'tiktok') {
    checks.push(check(
      'media_required',
      'Media attached',
      hasMedia,
      `${platform === 'instagram' ? 'Instagram' : 'TikTok'} requires at least one image or video URL — text-only posts are not supported.`,
    ));
  }

  if (hasMedia && ['instagram', 'tiktok', 'threads'].includes(platform)) {
    const allHttps = urls.every(isHttpsUrl);
    checks.push(check(
      'media_https',
      'Public HTTPS media URLs',
      allHttps,
      'All media URLs must use https:// so the platform can fetch them. Localhost or http:// links will fail.',
    ));
  }

  if (hasMedia && (platform === 'twitter' || platform === 'linkedin')) {
    const hasVideo = urls.some(isVideoUrl);
    checks.push(check(
      'no_video',
      'No video URLs',
      !hasVideo,
      `${platform === 'twitter' ? 'X / Twitter' : 'LinkedIn'} native publish does not support video yet. Use images or text only.`,
    ));
  }

  if (platform === 'instagram' && hasMedia && urls.length >= 2) {
    const hasVideo = urls.some(isVideoUrl);
    checks.push(check(
      'carousel_images',
      'Carousel uses images only',
      !hasVideo,
      'Instagram carousels support images only. Use one video URL for a Reel.',
    ));
  }

  if (platform === 'tiktok' && hasMedia && urls.length > 1) {
    const hasVideo = urls.some(isVideoUrl);
    checks.push(check(
      'tiktok_single_video',
      'TikTok video posts use one URL',
      !hasVideo || urls.length === 1,
      'TikTok allows one video per post. Use multiple image URLs for a photo post.',
    ));
  }

  if (!NATIVE_PROVIDERS.includes(platform)) {
    checks.push(check(
      'native_supported',
      'Native publish supported',
      false,
      `${platform} cannot be scheduled for native publish.`,
    ));
  }

  const valid = checks.every((c) => c.passed);
  return { valid, checks };
}

function check(id, label, passed, failMessage) {
  return {
    id,
    label,
    passed,
    message: passed ? null : failMessage,
  };
}

/** @returns {string|null} One-line summary of what the platform needs */
export function platformScheduleHint(provider) {
  const platform = normalizePlatform(provider);
  const hints = {
    twitter: 'Text caption required. Optional: up to 4 image URLs (no video).',
    facebook: 'Text caption required. Optional: image or video URLs.',
    instagram: 'Caption + at least one public HTTPS image or video URL required (no text-only).',
    tiktok: 'Caption + at least one public HTTPS image or video URL required.',
    threads: 'Caption and/or public HTTPS image or video URL.',
    linkedin: 'Text caption required. Optional: image URLs or article link (no video).',
  };
  return hints[platform] ?? null;
}

export function isPackageCompatible(pkg, provider) {
  if (!pkg || !provider) return false;
  return validateScheduleContent(pkg, provider).valid;
}

/** Validate a draft against its own platform for native publish readiness. */
export function validateDraftForNativePublish(pkg) {
  if (!pkg?.platform) {
    return { publishReady: false, embedOnly: false, issues: ['Missing platform on content package.'] };
  }

  const platform = normalizePlatform(pkg.platform);

  if (!NATIVE_PROVIDERS.includes(platform)) {
    return { publishReady: null, embedOnly: true, issues: [] };
  }

  const result = validateScheduleContent(pkg, platform);
  const issues = result.checks
    .filter((c) => !c.passed && c.id !== 'platform_match')
    .map((c) => c.message)
    .filter(Boolean);

  return {
    publishReady: result.valid,
    embedOnly: false,
    issues,
  };
}

/** @returns {string|null} Short label for draft list rows */
export function draftIssueSummary(pkg) {
  const { publishReady, embedOnly, issues } = validateDraftForNativePublish(pkg);
  if (embedOnly || publishReady) return null;
  return issues[0] ?? 'Not ready for native publish';
}

export function draftHasPublishIssue(pkg) {
  const { publishReady, embedOnly } = validateDraftForNativePublish(pkg);
  return !embedOnly && publishReady === false;
}

export function isNativePublishPlatform(platform) {
  return NATIVE_PROVIDERS.includes(normalizePlatform(platform));
}
