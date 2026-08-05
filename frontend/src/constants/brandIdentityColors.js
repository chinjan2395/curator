/**
 * Which brand identity colour seeds which embed appearance colour.
 *
 * Keys are paths inside the Publish-shaped `colors` group, values are keys of
 * the kit's own identity palette. A new Master kit is seeded with this map
 * server-side (`BrandKitExpandedSettings::IDENTITY_COLOR_MAP`); this copy backs
 * the editor's "Apply to embed appearance" button so a user can re-sync after
 * changing the palette. `BrandKitIdentitySeedTest` fails if the two drift.
 */
export const IDENTITY_TO_EMBED_COLOR = {
  post_text: 'text',
  'post_bg.color': 'background',
  post_link: 'primary',
  post_button: 'accent',
  post_icon: 'secondary',
  post_date: 'secondary',
  header_text: 'secondary',
  footer_text: 'text',
};

/**
 * Applies the map to a Publish-shaped `colors` group, in place.
 *
 * @param {object} target the draft's `colors` group
 * @param {object} identity the kit's `colors` (identity) group
 */
export function applyIdentityColors(target, identity) {
  if (!target || !identity) return;

  // The identity `background` colour is only visible once the card background
  // is switched on, so seeding implies enabling it.
  target.post_bg = { ...(target.post_bg || {}), enabled: true };

  Object.entries(IDENTITY_TO_EMBED_COLOR).forEach(([path, sourceKey]) => {
    const value = identity[sourceKey];
    if (typeof value !== 'string' || value === '') return;

    const [head, tail] = path.split('.');
    if (tail) target[head] = { ...(target[head] || {}), [tail]: value };
    else target[head] = value;
  });
}
