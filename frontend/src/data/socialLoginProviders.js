import axios from 'axios';

/** Metadata for buttons; visibility comes from GET /api/auth/social/providers (env-backed). */
export const ALL_SOCIAL_LOGIN_PROVIDERS = [
  {
    id: 'google',
    label: 'Google',
    icon: `<svg viewBox="0 0 24 24" class="w-4 h-4"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>`,
  },
  {
    id: 'facebook',
    label: 'Facebook',
    icon: `<svg viewBox="0 0 24 24" class="w-4 h-4"><path fill="#1877F2" d="M24 12.073C24 5.406 18.627 0 12 0S0 5.406 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.931-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>`,
  },
  {
    id: 'github',
    label: 'GitHub',
    icon: `<svg viewBox="0 0 24 24" class="w-4 h-4"><path fill="currentColor" d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>`,
  },
  {
    id: 'twitter',
    label: 'X / Twitter',
    icon: `<svg viewBox="0 0 24 24" class="w-4 h-4"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.264 5.633 5.9-5.633zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
  },
];

/**
 * Responsive grid for social login/signup buttons based on how many providers are enabled.
 * @param {number} count
 */
export function socialLoginButtonGridClass(count) {
  if (count <= 1) {
    return 'grid grid-cols-1 gap-2 max-w-sm mx-auto w-full';
  }
  if (count === 2) {
    return 'grid grid-cols-2 gap-2';
  }
  if (count === 3) {
    return 'grid grid-cols-1 gap-2 sm:grid-cols-3';
  }
  return 'grid grid-cols-2 gap-2 md:grid-cols-4';
}

/**
 * @returns {Promise<{ providers: typeof ALL_SOCIAL_LOGIN_PROVIDERS; notice: string }>}
 */
export async function fetchEnabledSocialLoginProviders() {
  const { data } = await axios.get('/api/auth/social/providers');
  const enabled = new Set(Array.isArray(data.providers) ? data.providers : []);
  const providers = ALL_SOCIAL_LOGIN_PROVIDERS.filter((p) => enabled.has(p.id));
  const notice =
    typeof data.social_login_notice === 'string' && data.social_login_notice.trim()
      ? data.social_login_notice.trim()
      : '';

  return { providers, notice };
}
