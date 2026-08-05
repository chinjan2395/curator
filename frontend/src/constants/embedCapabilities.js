/**
 * Which embed appearance settings apply to which feed layout.
 *
 * This is the single source of truth for option visibility. Before it existed,
 * `Publish.vue` carried ~10 ad-hoc booleans that all collapsed to
 * "is this showcase carousel?", which both hid options showcase genuinely
 * supports (the whole Widget tab, thumbnail sizing) and showed options other
 * layouts silently ignore.
 *
 * The matrix is a *presentation* concern only. The backend
 * (`PublishSettings::validateAndNormalize`) stays layout-agnostic and never
 * strips non-applicable keys — switching layout and back must not lose a
 * user's tuning, and brand kits carry a full settings tree across workspaces
 * whose layouts differ.
 */

export const EMBED_LAYOUTS = [
  'waterfall',
  'grid',
  'grid_carousel',
  'carousel',
  'showcase_carousel',
  'mosaic',
  'tetris',
  'select',
  'cover_flow',
  'list',
  'stagger',
  'layers',
];

const ALL = EMBED_LAYOUTS;
const except = (...omit) => ALL.filter((layout) => !omit.includes(layout));
const SHOWCASE = ['showcase_carousel'];

/** Layouts that read `--crt-post-min` to size their tracks. */
const MIN_WIDTH_LAYOUTS = ['grid', 'grid_carousel', 'stagger'];

/** `layers` positions cards absolutely, so flow gap has no meaning there. */
const GAP_LAYOUTS = except('layers');

const isAspect = (s) => s?.feed?.media_size_mode === 'aspect';
const isFixedHeight = (s) => s?.feed?.media_size_mode === 'fixed';

/**
 * One entry per setting path.
 *
 * - `tab` / `group` — where the control lives in the appearance panels.
 * - `layouts` — the layouts where the setting is meaningful.
 * - `dependsOn` — extra predicate over the settings tree, for controls that
 *   only make sense once a mode is selected (e.g. aspect ratio needs
 *   `media_size_mode === 'aspect'`).
 * - `status: 'planned'` — the key is stored and validated but nothing reads it
 *   yet. Never rendered; keeps the matrix honest instead of shipping a control
 *   that does nothing.
 * - `label` — used by consumers that annotate rather than hide (the brand kit
 *   editor), so a field can say which layouts it applies to.
 */
export const EMBED_CAPABILITIES = {
  // ---- Layout tab -------------------------------------------------------
  feed_style: { tab: 'layout', group: 'style', layouts: ALL, label: 'Feed style' },

  'feed.lazy_load': { tab: 'layout', group: 'loading', layouts: ALL, label: 'Lazy load' },
  'feed.show_load_more': { tab: 'layout', group: 'loading', layouts: ALL, label: 'Load more button' },
  'feed.posts_per_page': { tab: 'layout', group: 'loading', layouts: ALL, label: 'Posts per page' },
  'feed.post_min_width': {
    tab: 'layout',
    group: 'loading',
    layouts: MIN_WIDTH_LAYOUTS,
    label: 'Min post width',
  },

  'feed.media_size_mode': { tab: 'layout', group: 'thumbnails', layouts: ALL, label: 'Thumbnail sizing' },
  'feed.media_aspect_ratio': {
    tab: 'layout',
    group: 'thumbnails',
    layouts: ALL,
    dependsOn: isAspect,
    label: 'Aspect ratio',
  },
  'feed.media_height': {
    tab: 'layout',
    group: 'thumbnails',
    layouts: ALL,
    dependsOn: isFixedHeight,
    label: 'Thumbnail height',
  },
  'feed.media_fit': {
    tab: 'layout',
    group: 'thumbnails',
    layouts: ALL,
    dependsOn: (s) => isAspect(s) || isFixedHeight(s),
    label: 'Image fit',
  },
  'feed.showcase_card_width': {
    tab: 'layout',
    group: 'thumbnails',
    layouts: SHOWCASE,
    label: 'Card width',
  },

  // ---- Posts tab --------------------------------------------------------
  'post.show_titles': { tab: 'posts', group: 'shows', layouts: ALL, label: 'Title' },
  'post.show_platform_icon': { tab: 'posts', group: 'shows', layouts: ALL, label: 'Platform icon' },
  'post.show_feed_name': { tab: 'posts', group: 'shows', layouts: ALL, label: 'Feed / account name' },
  // Showcase draws its share control from `showcase_share_icon` instead.
  'post.show_share_icons': { tab: 'posts', group: 'shows', layouts: except('showcase_carousel'), label: 'Share icons' },
  'post.show_likes': { tab: 'posts', group: 'shows', layouts: except('showcase_carousel'), label: 'Likes' },
  'post.show_comments': { tab: 'posts', group: 'shows', layouts: except('showcase_carousel'), label: 'Comments' },
  'post.autoplay_videos': { tab: 'posts', group: 'shows', layouts: ALL, label: 'Autoplay videos' },

  'post.source_row_layout': {
    tab: 'posts',
    group: 'source_row',
    layouts: except('showcase_carousel'),
    label: 'Source row layout',
  },
  'post.source_row_alignment': {
    tab: 'posts',
    group: 'source_row',
    layouts: except('showcase_carousel'),
    label: 'Source row alignment',
  },

  'post.showcase_content_alignment': { tab: 'posts', group: 'showcase', layouts: SHOWCASE, label: 'Content alignment' },
  'post.showcase_share_icon': { tab: 'posts', group: 'showcase', layouts: SHOWCASE, label: 'Share icon' },
  // ---- Colors tab -------------------------------------------------------
  'colors.post_icon': { tab: 'colors', group: 'post_colors', layouts: ALL, label: 'Icon colour' },
  'colors.post_text': { tab: 'colors', group: 'post_colors', layouts: ALL, label: 'Text colour' },
  'colors.post_date': { tab: 'colors', group: 'post_colors', layouts: ALL, label: 'Date colour' },
  'colors.post_link': { tab: 'colors', group: 'post_colors', layouts: ALL, label: 'Link colour' },
  'colors.post_button': { tab: 'colors', group: 'post_colors', layouts: ALL, label: 'Button colour' },
  'colors.post_border': { tab: 'colors', group: 'surface', layouts: ALL, label: 'Post border' },
  'colors.post_bg': { tab: 'colors', group: 'surface', layouts: ALL, label: 'Post background' },

  // The header (source row) and footer used to borrow `post_date` / `post_text`
  // with no control of their own, and their icon colours lived on the Posts
  // tab — which is why they read as missing. All four now sit together.
  'colors.header_text': { tab: 'colors', group: 'header_footer', layouts: ALL, label: 'Header text' },
  'post.platform_icon_color_mode': {
    tab: 'colors',
    group: 'header_footer',
    layouts: ALL,
    label: 'Header icon colour',
  },
  'post.platform_icon_color': {
    tab: 'colors',
    group: 'header_footer',
    layouts: ALL,
    dependsOn: (s) => s?.post?.platform_icon_color_mode === 'custom',
    label: 'Custom header icon colour',
  },
  'colors.footer_text': { tab: 'colors', group: 'header_footer', layouts: SHOWCASE, label: 'Footer text' },
  'post.showcase_share_icon_color_mode': {
    tab: 'colors',
    group: 'header_footer',
    layouts: SHOWCASE,
    dependsOn: (s) => s?.post?.showcase_share_icon !== 'none',
    label: 'Footer icon colour',
  },
  'post.showcase_share_icon_color': {
    tab: 'colors',
    group: 'header_footer',
    layouts: SHOWCASE,
    dependsOn: (s) =>
      s?.post?.showcase_share_icon !== 'none' && s?.post?.showcase_share_icon_color_mode === 'custom',
    label: 'Custom footer icon colour',
  },
  'colors.showcase_shell_bg': {
    tab: 'colors',
    group: 'surface',
    layouts: SHOWCASE,
    label: 'Carousel shell background',
  },

  // ---- Widget tab -------------------------------------------------------
  'widget.theme': { tab: 'widget', group: 'style', layouts: ALL, label: 'Theme' },
  // Only waterfall has a column-count reader; grid/select/stagger derive their
  // columns from `post_min_width`, so exposing it there would fight that field.
  'widget.columns': { tab: 'widget', group: 'style', layouts: ['waterfall'], label: 'Columns' },
  'widget.gap': { tab: 'widget', group: 'style', layouts: GAP_LAYOUTS, label: 'Gap' },
  'widget.border_radius': { tab: 'widget', group: 'style', layouts: ALL, label: 'Border radius' },
  'widget.font_family': { tab: 'widget', group: 'style', layouts: ALL, label: 'Font family' },
  'widget.animation': { tab: 'widget', group: 'style', layouts: ['stagger'], label: 'Animation' },
  'widget.click_action': { tab: 'widget', group: 'style', layouts: ALL, label: 'Click action' },
  'widget.auto_refresh': { tab: 'widget', group: 'style', layouts: ALL, label: 'Auto-refresh' },
  // Applied server-side by PublicFeedController, so layout-agnostic.
  'widget.platform_filters': { tab: 'widget', group: 'filters', layouts: ALL, label: 'Platform filters' },
  'widget.content_type_filters': { tab: 'widget', group: 'filters', layouts: ALL, label: 'Content type filters' },

  // ---- Branding tab -----------------------------------------------------
  // The badge overlay is only drawn by the showcase renderer. Turning it on for
  // standard cards would silently add a prominent glyph to every existing
  // non-showcase embed (it defaults to `show: true`), so it stays scoped.
  'branding.media_badge': { tab: 'branding', group: 'slots', layouts: SHOWCASE, label: 'Media badge' },
  'branding.source_icon': { tab: 'branding', group: 'slots', layouts: ALL, label: 'Feed icon' },
  // Only the showcase card has a footer to put an avatar in.
  'branding.account_avatar': { tab: 'branding', group: 'slots', layouts: SHOWCASE, label: 'Footer avatar' },
};

export const EMBED_TABS = [
  { key: 'layout', label: 'Layout' },
  { key: 'posts', label: 'Posts' },
  { key: 'colors', label: 'Colors' },
  { key: 'widget', label: 'Widget' },
  { key: 'branding', label: 'Branding' },
];

export function normalizeLayout(feedStyle) {
  return String(feedStyle || 'grid').replace(/-/g, '_');
}

/** Is this setting meaningful for `layout`, ignoring mode-dependent reveals? */
export function isApplicable(path, layout) {
  const def = EMBED_CAPABILITIES[path];
  if (!def) return false;
  if (def.status === 'planned') return false;
  return def.layouts.includes(normalizeLayout(layout));
}

/**
 * Full visibility map for one layout + settings tree.
 * `caps['widget.gap']` is true when the control should be rendered.
 */
export function resolveCapabilities(feedStyle, settings) {
  const layout = normalizeLayout(feedStyle);
  const out = {};
  Object.entries(EMBED_CAPABILITIES).forEach(([path, def]) => {
    out[path] =
      isApplicable(path, layout) && (typeof def.dependsOn !== 'function' || !!def.dependsOn(settings));
  });
  return out;
}

/** A tab is shown when at least one of its settings is visible. */
export function visibleTabKeys(caps) {
  return EMBED_TABS.filter((tab) =>
    Object.entries(EMBED_CAPABILITIES).some(([path, def]) => def.tab === tab.key && caps[path]),
  ).map((tab) => tab.key);
}

/** A group heading is shown when at least one of its settings is visible. */
export function hasGroup(caps, tab, group) {
  return Object.entries(EMBED_CAPABILITIES).some(
    ([path, def]) => def.tab === tab && def.group === group && caps[path],
  );
}

/** Human-readable list of layouts a setting applies to, for annotations. */
export function layoutsForPath(path) {
  return EMBED_CAPABILITIES[path]?.layouts ?? [];
}
