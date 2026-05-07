/* global CRT_POSTS_URL, CRT_PUBLIC_KEY, CRT_SETTINGS */
(function () {
  var POSTS_URL = typeof CRT_POSTS_URL !== 'undefined' ? CRT_POSTS_URL : '';
  var PUBLIC_KEY = typeof CRT_PUBLIC_KEY !== 'undefined' ? CRT_PUBLIC_KEY : '';
  var SETTINGS = typeof CRT_SETTINGS !== 'undefined' ? CRT_SETTINGS : {};

  var containers = Array.prototype.slice.call(
    document.querySelectorAll('[data-curator-feed="' + PUBLIC_KEY + '"]'),
  );
  if (containers.length === 0) {
    var byId = document.getElementById('curator-feed-' + PUBLIC_KEY) || document.getElementById('curator-feed');
    if (byId) containers = [byId];
  }
  if (containers.length === 0 || !POSTS_URL) return;

  var feedStyle = String(SETTINGS.feed_style || 'grid').replace(/-/g, '_');
  var feedOpts = SETTINGS.feed || {};
  var postOpts = SETTINGS.post || {};
  var colors = SETTINGS.colors || {};
  var branding = SETTINGS.branding || {};
  var mediaBadgeCfg = branding.media_badge || {};
  var sourceIconCfg = branding.source_icon || {};
  var accountAvatarCfg = branding.account_avatar || {};

  var perPage = Math.max(1, Math.min(parseInt(feedOpts.posts_per_page, 10) || 12, 100));
  var postMin = Math.max(120, Math.min(parseInt(feedOpts.post_min_width, 10) || 260, 600));
  var lazyLoad = feedOpts.lazy_load !== false;
  var showLoadMore = feedOpts.show_load_more !== false;

  var SHARE_SHOWCASE_UPLOAD =
    '<svg width="18" height="15" viewBox="0 0 18 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.65875 0.821899V13.0736L17.2182 6.94777L9.65875 0.821899Z" fill="currentColor"></path><path fill-rule="evenodd" clip-rule="evenodd" d="M0.138031 13.1146C0.138031 8.46583 3.7997 4.60382 10.2833 4.60382V9.39066C10.2833 9.39066 6.07325 8.10554 1.03012 13.1146C0.76259 13.1439 0.138031 13.1146 0.138031 13.1146Z" fill="currentColor"></path></svg>';

  var SHARE_SHOWCASE_ARROW =
    '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M17 7H9M17 7v8"/></svg>';

  var NAV_CHEV_LEFT =
    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18 9 12l6-6"/></svg>';

  var NAV_CHEV_RIGHT =
    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>';

  function clamp(s, n) {
    s = String(s || '');
    return s.length > n ? s.slice(0, n - 1) + '…' : s;
  }

  function fmtDate(v) {
    try {
      return new Date(v).toLocaleDateString();
    } catch (e) {
      return '';
    }
  }

  function fmtDateShowcase(v) {
    try {
      return new Date(v)
        .toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
        .toUpperCase();
    } catch (e) {
      return '';
    }
  }

  function stripHashtags(raw) {
    var tags = [];
    var text = String(raw || '').replace(/#([a-zA-Z0-9_]+)/g, function (_, w) {
      tags.push('#' + w);
      return '';
    });
    text = text.replace(/\s+/g, ' ').trim();
    return { plain: text, tags: tags };
  }

  function accountDisplayLabel(p) {
    var a = String((p && p.account_label) || '').trim();
    if (a) return a;
    return String((p && p.feed_name) || (p && p.provider) || '').trim();
  }

  function showcaseHandle(p) {
    var raw = accountDisplayLabel(p);
    if (!raw) raw = String((p && p.provider) || 'social').trim();
    if (!raw) return '@social';
    if (raw.charAt(0) === '@') return raw;
    return '@' + raw;
  }

  function showcaseAvatarLetter(p) {
    var raw = accountDisplayLabel(p);
    var n = raw.replace(/^@+/, '').trim() || String((p && p.provider) || '?').trim();
    return n ? n.charAt(0).toUpperCase() : '?';
  }

  function brandingImg(url, className) {
    var img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.loading = 'lazy';
    img.className = className || 'crt-brand-img';
    img.referrerPolicy = 'no-referrer';
    return img;
  }

  function normalizeUnderscore(v) {
    return String(v || '').replace(/-/g, '_');
  }

  function appendMediaBadgeOverlay(media, provider) {
    var mb = mediaBadgeCfg;
    var first = media.firstChild;
    var isIframe = first && first.tagName === 'IFRAME';
    if (isIframe) return;
    if (mb.show === false || mb.image_source === 'none') return;

    var posRaw = normalizeUnderscore(mb.position || 'center');
    var posAllowed = {
      center: 1,
      top_left: 1,
      top_right: 1,
      bottom_left: 1,
      bottom_right: 1,
    };
    if (!posAllowed[posRaw]) posRaw = 'center';

    var overlay = document.createElement('div');
    overlay.className =
      'crt-media-overlay crt-media-overlay--' + posRaw.replace(/_/g, '-');

    var useCustom = mb.image_source === 'custom' && mb.custom_url;
    if (useCustom) {
      overlay.appendChild(brandingImg(mb.custom_url, 'crt-brand-img crt-brand-img--media'));
    } else {
      var badge = providerBadgeEl(provider);
      overlay.appendChild(badge);
    }
    media.appendChild(overlay);
  }

  function appendSourceRow(body, p) {
    var si = sourceIconCfg;
    var srcRow = document.createElement('div');
    srcRow.className = 'crt-showcase-source';

    var fn = document.createElement('span');
    fn.className = 'crt-showcase-feed-name';
    fn.textContent = accountDisplayLabel(p) || p.provider || 'Social';

    var iconWrap = null;
    if (si.show !== false && si.image_source !== 'none') {
      iconWrap = document.createElement('span');
      iconWrap.className = 'crt-showcase-source-icon';
      var useCustom = si.image_source === 'custom' && si.custom_url;
      if (useCustom) {
        iconWrap.appendChild(brandingImg(si.custom_url, 'crt-brand-img crt-brand-img--inline'));
      } else {
        var mini = providerBadgeEl(p.provider);
        mini.className = 'crt-showcase-provider-icon crt-showcase-provider-icon--inline';
        iconWrap.appendChild(mini);
      }
    }

    var order = normalizeUnderscore(si.position || 'before_name');
    if (order !== 'after_name') order = 'before_name';

    if (order === 'after_name') {
      srcRow.appendChild(fn);
      if (iconWrap) srcRow.appendChild(iconWrap);
    } else {
      if (iconWrap) srcRow.appendChild(iconWrap);
      srcRow.appendChild(fn);
    }

    body.appendChild(srcRow);
  }

  function appendShowcaseFooter(body, p, linkHref) {
    var avCfg = accountAvatarCfg;
    var foot = document.createElement('div');
    foot.className = 'crt-showcase-footer';

    var avatarEl = null;
    if (avCfg.show !== false && avCfg.image_source !== 'none') {
      var avSrc = String(avCfg.image_source || 'connected').toLowerCase();
      var connectedUrl = String((p && p.account_avatar_url) || '').trim();
      var customUrl = avCfg.custom_url ? String(avCfg.custom_url).trim() : '';
      var imgUrl =
        avSrc === 'custom' && customUrl !== ''
          ? customUrl
          : avSrc === 'connected' && connectedUrl !== ''
            ? connectedUrl
            : '';
      if (imgUrl !== '') {
        avatarEl = brandingImg(imgUrl, 'crt-showcase-avatar crt-showcase-avatar--img');
      } else if (avSrc === 'initial' || avSrc === 'connected' || (avSrc === 'custom' && customUrl === '')) {
        avatarEl = document.createElement('span');
        avatarEl.className = 'crt-showcase-avatar';
        avatarEl.textContent = showcaseAvatarLetter(p);
      }
    }

    var meta = document.createElement('div');
    meta.className = 'crt-showcase-meta-stack';
    var h = document.createElement('span');
    h.className = 'crt-showcase-handle';
    h.textContent = showcaseHandle(p);
    meta.appendChild(h);
    var d = document.createElement('span');
    d.className = 'crt-showcase-foot-date';
    d.textContent = fmtDateShowcase(p.posted_at);
    meta.appendChild(d);

    var shareUrl = p.video_url || linkHref;
    var shareMode = normalizeUnderscore(postOpts.showcase_share_icon || 'upload_share');
    if (shareMode !== 'arrow' && shareMode !== 'upload_share' && shareMode !== 'none') {
      shareMode = 'upload_share';
    }

    var shareEl = null;
    if (shareMode !== 'none' && shareUrl && shareUrl !== '#') {
      shareEl = buildShareTooltip({
        url: shareUrl,
        wrapperClass: 'crt-showcase-share',
        triggerClass: 'crt-showcase-share-btn',
        menuClass: 'crt-showcase-share-tooltip',
        triggerHtml: shareMode === 'arrow' ? SHARE_SHOWCASE_ARROW : SHARE_SHOWCASE_UPLOAD,
        providers: [
          { provider: 'twitter', label: 'Share on X' },
          { provider: 'facebook', label: 'Share on Facebook' },
        ],
      });
    }

    var footPos = normalizeUnderscore(avCfg.position || 'footer_start');
    if (footPos !== 'footer_end') footPos = 'footer_start';

    if (footPos === 'footer_end') {
      foot.appendChild(meta);
      if (shareEl) foot.appendChild(shareEl);
      if (avatarEl) foot.appendChild(avatarEl);
    } else {
      if (avatarEl) foot.appendChild(avatarEl);
      foot.appendChild(meta);
      if (shareEl) foot.appendChild(shareEl);
    }

    body.appendChild(foot);
  }

  function providerBadgeEl(provider) {
    var p = String(provider || '').toLowerCase();
    var wrap = document.createElement('span');
    wrap.className = 'crt-showcase-provider-icon';
    wrap.setAttribute('aria-hidden', 'true');
    var svg = '';
    if (p === 'youtube') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="40" viewBox="0 0 56 40" fill="none"><rect width="56" height="40" rx="8" fill="#FF0000"/><path d="M23 12v16l14-8-14-8z" fill="#fff"/></svg>';
    } else if (p === 'facebook') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" fill="none"><circle cx="22" cy="22" r="22" fill="#1877F2"/><path d="M24.5 15.2h2.9v-3.4c0-.1 0-1.6.5-2.8.5-1.3 1.5-2.6 3.6-2.6 2.9 0 4.2.4 4.2.4l-.6 3.5s-1-.3-2-.3c-1 0-1.2.5-1.2 1.2v3h4l-.3 3.4h-3.7V37h-4.5V21.6h-3v-3.4h3V15.2z" fill="#fff"/></svg>';
    } else if (p === 'instagram') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none"><rect width="48" height="48" rx="12" fill="#E4405F"/><path d="M24 14.5c3.5 0 3.9 0 5.3.1 2.6.1 3.8 1.2 3.9 3.9 0 1.4.1 1.8.1 5.3s0 3.9-.1 5.3c-.1 2.7-1.3 3.8-3.9 3.9-1.4 0-1.8.1-5.3.1s-3.9 0-5.3-.1c-2.6-.1-3.8-1.2-3.9-3.9 0-1.4-.1-1.8-.1-5.3s0-3.9.1-5.3c.1-2.7 1.3-3.8 3.9-3.9 1.4-.1 1.8-.1 5.3-.1zm0-2.4c-3.6 0-4 0-5.4.1-3.8.2-5.8 2.1-6 6-.1 1.4-.1 1.8-.1 5.4s0 4 .1 5.4c.2 3.8 2.2 5.8 6 6 1.4.1 1.8.1 5.4.1s4 0 5.4-.1c3.8-.2 5.8-2.2 6-6 .1-1.4.1-1.8.1-5.4s0-4-.1-5.4c-.2-3.8-2.2-5.8-6-6-1.4-.1-1.8-.1-5.4-.1zm0 6.6a8.5 8.5 0 100 17 8.5 8.5 0 000-17zm0 14a5.5 5.5 0 110-11 5.5 5.5 0 010 11zm10.8-14.3a2 2 0 11-4 0 2 2 0 014 0z" fill="#fff"/></svg>';
    } else if (p === 'tiktok') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" fill="none"><circle cx="22" cy="22" r="22" fill="#000"/><path d="M26 12v5.2c-1.6-.9-3.5-1.2-5.3-.9v4.2c1.2-.2 2.5 0 3.6.6 1.3.7 2.2 2 2.5 3.5.4 2.1-.3 4.3-1.9 5.7-2 1.8-5.2 1.8-7.2 0-1.6-1.5-2.3-3.7-1.9-5.8h4.1c-.2 1.1.4 2.2 1.4 2.7 1.1.6 2.5.3 3.2-.7.6-.9.5-2.2-.3-2.9-.9-.8-2.3-.9-3.3-.2V12h4z" fill="#fff"/></svg>';
    } else if (p === 'twitter') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" fill="none"><circle cx="22" cy="22" r="22" fill="#000"/><path d="M13 13h5l5.4 7.3L29 13h4l-7.8 10.4L34 31h-5l-6-8.1L15 31h-4l8.4-11.2L13 13z" fill="#fff"/></svg>';
    } else if (p === 'threads') {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" fill="#101419"/><path d="M12.2 7.5c-2.8 0-4.8 1.4-5.5 3.6-.2.6-.3 1.2-.3 1.9 0 2.6 1.6 4.4 4.2 4.8 1.2.2 2.4-.1 3.3-.7a3.7 3.7 0 001.4-2.4h-2.1c-.3 1.1-1.2 1.8-2.5 1.8-1.6 0-2.6-1.1-2.6-2.9 0-2 1.3-3.4 3.4-3.4 1 0 1.8.4 2.3 1l1.5-.9c-.9-1.2-2.4-1.9-4.2-1.9z" fill="#fff"/></svg>';
    } else {
      svg =
        '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" fill="none"><circle cx="22" cy="22" r="22" fill="rgba(255,255,255,.2)"/><circle cx="22" cy="22" r="8" stroke="#fff" stroke-width="2" fill="none"/></svg>';
    }
    wrap.innerHTML = svg;
    return wrap;
  }

  function youtubeId(url) {
    var m = String(url || '').match(
      /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([^&?/]+)/,
    );
    return m ? m[1] : null;
  }

  function platformInlineSvg(provider) {
    var p = String(provider || '').toLowerCase();
    if (p === 'youtube') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 56 40" fill="none" aria-hidden="true"><rect width="56" height="40" rx="8" fill="#FF0000"/><path d="M23 12v16l14-8-14-8z" fill="#fff"/></svg>';
    }
    if (p === 'facebook') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 44 44" fill="none" aria-hidden="true"><circle cx="22" cy="22" r="22" fill="#1877F2"/><path d="M24.5 15.2h2.9v-3.4c0-.1 0-1.6.5-2.8.5-1.3 1.5-2.6 3.6-2.6 2.9 0 4.2.4 4.2.4l-.6 3.5s-1-.3-2-.3c-1 0-1.2.5-1.2 1.2v3h4l-.3 3.4h-3.7V37h-4.5V21.6h-3v-3.4h3V15.2z" fill="#fff"/></svg>';
    }
    if (p === 'instagram') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect width="48" height="48" rx="12" fill="#E4405F"/><path d="M24 14.5c3.5 0 3.9 0 5.3.1 2.6.1 3.8 1.2 3.9 3.9 0 1.4.1 1.8.1 5.3s0 3.9-.1 5.3c-.1 2.7-1.3 3.8-3.9 3.9-1.4 0-1.8.1-5.3.1s-3.9 0-5.3-.1c-2.6-.1-3.8-1.2-3.9-3.9 0-1.4-.1-1.8-.1-5.3s0-3.9.1-5.3c.1-2.7 1.3-3.8 3.9-3.9 1.4-.1 1.8-.1 5.3-.1zm0-2.4c-3.6 0-4 0-5.4.1-3.8.2-5.8 2.1-6 6-.1 1.4-.1 1.8-.1 5.4s0 4 .1 5.4c.2 3.8 2.2 5.8 6 6 1.4.1 1.8.1 5.4.1s4 0 5.4-.1c3.8-.2 5.8-2.2 6-6 .1-1.4.1-1.8.1-5.4s0-4-.1-5.4c-.2-3.8-2.2-5.8-6-6-1.4-.1-1.8-.1-5.4-.1zm0 6.6a8.5 8.5 0 100 17 8.5 8.5 0 000-17zm0 14a5.5 5.5 0 110-11 5.5 5.5 0 010 11zm10.8-14.3a2 2 0 11-4 0 2 2 0 014 0z" fill="#fff"/></svg>';
    }
    if (p === 'tiktok') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 44 44" fill="none" aria-hidden="true"><circle cx="22" cy="22" r="22" fill="#000"/><path d="M26 12v5.2c-1.6-.9-3.5-1.2-5.3-.9v4.2c1.2-.2 2.5 0 3.6.6 1.3.7 2.2 2 2.5 3.5.4 2.1-.3 4.3-1.9 5.7-2 1.8-5.2 1.8-7.2 0-1.6-1.5-2.3-3.7-1.9-5.8h4.1c-.2 1.1.4 2.2 1.4 2.7 1.1.6 2.5.3 3.2-.7.6-.9.5-2.2-.3-2.9-.9-.8-2.3-.9-3.3-.2V12h4z" fill="#fff"/></svg>';
    }
    if (p === 'twitter') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 44 44" fill="none" aria-hidden="true"><circle cx="22" cy="22" r="22" fill="#000"/><path d="M13 13h5l5.4 7.3L29 13h4l-7.8 10.4L34 31h-5l-6-8.1L15 31h-4l8.4-11.2L13 13z" fill="#fff"/></svg>';
    }
    if (p === 'threads') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#101419"/><path d="M12.2 7.5c-2.8 0-4.8 1.4-5.5 3.6-.2.6-.3 1.2-.3 1.9 0 2.6 1.6 4.4 4.2 4.8 1.2.2 2.4-.1 3.3-.7a3.7 3.7 0 001.4-2.4h-2.1c-.3 1.1-1.2 1.8-2.5 1.8-1.6 0-2.6-1.1-2.6-2.9 0-2 1.3-3.4 3.4-3.4 1 0 1.8.4 2.3 1l1.5-.9c-.9-1.2-2.4-1.9-4.2-1.9z" fill="#fff"/></svg>';
    }
    if (p === 'rss') {
      return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path fill="#ea580c" d="M4 17a3 3 0 100 6 3 3 0 000-6zm-4-9v3c7.2 0 13 5.8 13 13h3C16 12.8 8.2 5 0 5zm0-5v3c11 0 20 9 20 20h3C23 9.9 14.1 0 0 0z"/></svg>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9.5" stroke="currentColor" stroke-width="1.75"/><path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>';
  }

  function platformIconInlineEl(provider) {
    var wrap = document.createElement('span');
    wrap.className = 'crt-platform-badge crt-platform-badge--inline';
    wrap.setAttribute('aria-hidden', 'true');
    wrap.innerHTML = platformInlineSvg(provider);
    return wrap;
  }

  function shareTargetUrl(provider, url) {
    var enc = encodeURIComponent(String(url || ''));
    if (provider === 'facebook') return 'https://www.facebook.com/sharer/sharer.php?u=' + enc;
    if (provider === 'linkedin') return 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc;
    return 'https://twitter.com/intent/tweet?url=' + enc;
  }

  function openShare(provider, url) {
    window.open(shareTargetUrl(provider, url), '_blank', 'noopener,noreferrer');
  }

  function sharePlatformSvg(provider) {
    var p = String(provider || '').toLowerCase();
    if (p === 'twitter') {
      return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L.8 2h6.5l4.4 5.9L18.9 2Z" /></svg>';
    }
    if (p === 'facebook') {
      return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7v-3.5h3.1V9.4c0-3.1 1.9-4.8 4.7-4.8 1.3 0 2.6.2 2.6.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12Z" /></svg>';
    }
    if (p === 'linkedin') {
      return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.05-1.86-3.05-1.86 0-2.14 1.45-2.14 2.95v5.67H9.33V9h3.42v1.56h.05c.48-.9 1.63-1.86 3.36-1.86 3.59 0 4.25 2.36 4.25 5.42v6.33ZM5.31 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14ZM7.09 20.45H3.54V9h3.55v11.45Z"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/></svg>';
  }

  function buildShareTooltip(options) {
    var wrap = document.createElement('div');
    wrap.className = options.wrapperClass;
    wrap.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
    });
    wrap.addEventListener('mousedown', function (e) {
      e.preventDefault();
      e.stopPropagation();
    });

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = options.triggerClass;
    trigger.setAttribute('aria-label', 'Share');
    trigger.title = 'Share';
    trigger.innerHTML = options.triggerHtml || SHARE_SHOWCASE_UPLOAD;

    var menu = document.createElement('div');
    menu.className = options.menuClass;

    function closeMenu() {
      menu.classList.remove('is-open');
    }

    (options.providers || []).forEach(function (entry) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'crt-showcase-share-platform';
      btn.setAttribute('aria-label', entry.label);
      btn.title = entry.label;
      btn.innerHTML = sharePlatformSvg(entry.provider);
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openShare(entry.provider, options.url);
        closeMenu();
      });
      menu.appendChild(btn);
    });

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      menu.classList.toggle('is-open');
    });

    document.addEventListener('click', closeMenu);
    wrap.appendChild(trigger);
    wrap.appendChild(menu);
    return wrap;
  }

  function renderStandardSourceRow(p) {
    var showIcon = postOpts.show_platform_icon !== false;
    var showName = postOpts.show_feed_name !== false;
    var label = accountDisplayLabel(p) || String(p.provider || '').trim();
    var prov = String(p.provider || '').trim();

    if (!showIcon && !showName) return null;
    if (!showIcon && (!showName || !label)) return null;
    if (!showName && (!showIcon || !prov)) return null;

    var layout = normalizeUnderscore(postOpts.source_row_layout || 'stacked');
    if (layout !== 'inline') layout = 'stacked';

    var align = normalizeUnderscore(postOpts.source_row_alignment || 'center');
    if (align !== 'start') align = 'center';

    var row = document.createElement('div');
    row.className =
      'crt-source-row crt-source-row--' +
      layout +
      ' crt-source-row--align-' +
      align;

    if (showIcon && prov) {
      row.appendChild(platformIconInlineEl(prov));
    }

    if (showName && label) {
      var lab = document.createElement('span');
      lab.className = 'crt-source-label';
      lab.textContent = label;
      row.appendChild(lab);
    }

    if (!row.hasChildNodes()) return null;
    return row;
  }

  function applyColorVars(el) {
    var c = colors;
    el.style.setProperty('--crt-icon', c.post_icon || '#64748b');
    el.style.setProperty('--crt-text', c.post_text || '#0f172a');
    el.style.setProperty('--crt-date', c.post_date || '#64748b');
    el.style.setProperty('--crt-link', c.post_link || '#2563eb');
    el.style.setProperty('--crt-btn', c.post_button || '#0f172a');
    el.style.setProperty('--crt-post-min', postMin + 'px');
    var b = c.post_border || {};
    if (b.enabled !== false) {
      el.style.setProperty('--crt-border', b.color || '#e2e8f0');
    } else {
      el.style.setProperty('--crt-border', 'transparent');
    }
    var g = c.post_bg || {};
    if (g.enabled !== false) {
      el.style.setProperty('--crt-card-bg', g.color || '#ffffff');
    } else {
      el.style.setProperty('--crt-card-bg', 'transparent');
    }
    var mode = normalizeUnderscore(postOpts.showcase_share_icon_color_mode || 'post_icon');
    var shareColor = c.post_icon || '#64748b';
    if (mode === 'post_text') shareColor = c.post_text || '#0f172a';
    else if (mode === 'post_button') shareColor = c.post_button || '#0f172a';
    else if (mode === 'custom') {
      shareColor = postOpts.showcase_share_icon_color || c.post_icon || '#64748b';
    }
    el.style.setProperty('--crt-showcase-share-color', shareColor);
  }

  function ensureInner(container) {
    container.classList.add('crt-wrap');
    var inner = container.querySelector('.crt-inner');
    if (!inner) {
      inner = document.createElement('div');
      inner.className = 'crt-inner';
      container.innerHTML = '';
      container.appendChild(inner);
    } else {
      inner.innerHTML = '';
    }
    inner.className = 'crt-inner crt-layout--' + feedStyle;
    return inner;
  }

  function addLoadRow(container, state) {
    var existing = container.querySelector('.crt-load-row');
    if (existing) {
      try {
        if (state.io) state.io.disconnect();
      } catch (e) {}
      state.io = null;
      existing.remove();
    }
    if (!lazyLoad && !showLoadMore) return;
    if (!state.hasMore) return;

    var row = document.createElement('div');
    row.className = 'crt-load-row';

    if (lazyLoad) {
      var sentinel = document.createElement('div');
      sentinel.className = 'crt-lazy-sentinel';
      sentinel.setAttribute('aria-hidden', 'true');
      row.appendChild(sentinel);
    } else if (showLoadMore) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'crt-load-more';
      btn.textContent = state.loading ? 'Loading…' : 'Load more';
      btn.disabled = state.loading;
      btn.onclick = function () {
        state.loadNext();
      };
      row.appendChild(btn);
    }
    container.appendChild(row);

    if (lazyLoad && state.hasMore) {
      state.io = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting && state.hasMore && !state.loading) {
              state.loadNext();
            }
          });
        },
        { root: null, rootMargin: '160px', threshold: 0 },
      );
      var sen = row.querySelector('.crt-lazy-sentinel');
      if (sen) state.io.observe(sen);
    }
  }

  function buildMedia(p) {
    var wrap = document.createElement('div');
    wrap.className = 'crt-media';
    var vid = p.video_url;
    var yid = postOpts.autoplay_videos ? youtubeId(vid) : null;
    if (yid) {
      var ifr = document.createElement('iframe');
      ifr.className = 'crt-yt';
      ifr.setAttribute(
        'src',
        'https://www.youtube.com/embed/' +
          yid +
          '?rel=0&modestbranding=1&autoplay=1&mute=1&playsinline=1',
      );
      ifr.setAttribute('title', p.title || 'Video');
      ifr.setAttribute('loading', 'lazy');
      ifr.setAttribute('allowfullscreen', '');
      wrap.appendChild(ifr);
      return wrap;
    }
    if (p.thumbnail_url) {
      var img = document.createElement('img');
      img.src = p.thumbnail_url;
      img.alt = p.title || 'Post';
      img.loading = 'lazy';
      wrap.appendChild(img);
      return wrap;
    }
    if (vid) {
      var ph = document.createElement('div');
      ph.className = 'crt-media-ph';
      ph.textContent = 'Open video';
      wrap.appendChild(ph);
    }
    return wrap;
  }

  function shareBar(url) {
    if (!url || url === '#') return null;
    return buildShareTooltip({
      url: url,
      wrapperClass: 'crt-share',
      triggerClass: 'crt-share-link crt-share-link--trigger',
      menuClass: 'crt-share-tooltip',
      triggerHtml: SHARE_SHOWCASE_UPLOAD,
      providers: [
        { provider: 'twitter', label: 'Share on X' },
        { provider: 'facebook', label: 'Share on Facebook' },
      ],
    });
  }

  function metaRow(p) {
    var row = document.createElement('div');
    row.className = 'crt-meta';
    var left = document.createElement('span');
    left.className = 'crt-meta-date';
    left.textContent = fmtDate(p.posted_at);
    row.appendChild(left);
    if (postOpts.show_likes || postOpts.show_comments) {
      var right = document.createElement('span');
      right.className = 'crt-meta-social';
      var bits = [];
      if (postOpts.show_likes) bits.push('♥');
      if (postOpts.show_comments) bits.push('💬');
      right.textContent = bits.join(' ');
      row.appendChild(right);
    }
    return row;
  }

  function wrapShowcaseViewport(container, inner) {
    if (container.querySelector('.crt-showcase-viewport')) return;
    container.classList.add('crt-wrap--showcase');
    var vp = document.createElement('div');
    vp.className = 'crt-showcase-viewport';
    container.replaceChild(vp, inner);
    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'crt-showcase-nav crt-showcase-nav--prev';
    prev.setAttribute('aria-label', 'Previous posts');
    prev.innerHTML = NAV_CHEV_LEFT;
    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'crt-showcase-nav crt-showcase-nav--next';
    next.setAttribute('aria-label', 'Next posts');
    next.innerHTML = NAV_CHEV_RIGHT;
    function step() {
      return Math.max(260, Math.floor(inner.clientWidth * 0.82));
    }
    prev.addEventListener('click', function () {
      inner.scrollBy({ left: -step(), behavior: 'smooth' });
    });
    next.addEventListener('click', function () {
      inner.scrollBy({ left: step(), behavior: 'smooth' });
    });
    vp.appendChild(prev);
    vp.appendChild(inner);
    vp.appendChild(next);
  }

  function renderShowcaseCard(p, index, linkHref) {
    var useIframe = postOpts.autoplay_videos && youtubeId(p.video_url);
    var root = document.createElement(useIframe ? 'div' : 'a');
    if (root.tagName === 'A') {
      root.href = linkHref || p.video_url || '#';
      root.target = '_blank';
      root.rel = 'noreferrer';
    }
    root.className = 'crt-card crt-card--showcase crt-link';
    root.setAttribute('data-crt-i', String(index));

    if (useIframe) {
      root.addEventListener('click', function () {
        var y = youtubeId(p.video_url);
        if (y) window.open('https://www.youtube.com/watch?v=' + y, '_blank', 'noreferrer');
      });
    }

    var media = buildMedia(p);
    if (media.hasChildNodes()) {
      appendMediaBadgeOverlay(media, p.provider);
      root.appendChild(media);
    }

    var body = document.createElement('div');
    body.className = 'crt-body crt-body--showcase';
    var sac = normalizeUnderscore(postOpts.showcase_content_alignment || 'start');
    if (sac === 'center') {
      body.classList.add('crt-showcase--align-center');
    }

    appendSourceRow(body, p);

    if (postOpts.show_titles !== false) {
      var title = document.createElement('div');
      title.className = 'crt-title crt-title--showcase';
      title.textContent = p.title || 'Untitled';
      body.appendChild(title);
    }

    var split = stripHashtags(p.content || '');
    var text = document.createElement('div');
    text.className = 'crt-text crt-text--showcase';
    text.textContent = clamp(split.plain, 260);
    body.appendChild(text);

    if (split.tags.length) {
      var htWrap = document.createElement('div');
      htWrap.className = 'crt-hashtags';
      split.tags.slice(0, 8).forEach(function (tag) {
        var s = document.createElement('span');
        s.className = 'crt-hashtag';
        s.textContent = tag;
        htWrap.appendChild(s);
      });
      body.appendChild(htWrap);
    }

    appendShowcaseFooter(body, p, linkHref);

    root.appendChild(body);
    return root;
  }

  function renderCard(p, index, linkHref) {
    if (feedStyle === 'showcase_carousel') {
      return renderShowcaseCard(p, index, linkHref);
    }
    var useIframe = postOpts.autoplay_videos && youtubeId(p.video_url);
    var a = document.createElement(useIframe ? 'div' : 'a');
    if (a.tagName === 'A') {
      a.href = linkHref || p.video_url || '#';
      a.target = '_blank';
      a.rel = 'noreferrer';
    }
    a.className = 'crt-card crt-link';
    a.setAttribute('data-crt-i', String(index));

    if (useIframe) {
      a.addEventListener('click', function (e) {
        var y = youtubeId(p.video_url);
        if (y) window.open('https://www.youtube.com/watch?v=' + y, '_blank', 'noreferrer');
      });
    }

    var media = buildMedia(p);
    if (media.hasChildNodes()) a.appendChild(media);

    var body = document.createElement('div');
    body.className = 'crt-body';

    var srcRowStd = renderStandardSourceRow(p);
    if (srcRowStd) body.appendChild(srcRowStd);

    if (postOpts.show_titles !== false) {
      var title = document.createElement('div');
      title.className = 'crt-title';
      title.textContent = p.title || 'Untitled';
      body.appendChild(title);
    }

    var text = document.createElement('div');
    text.className = 'crt-text';
    text.textContent = clamp(p.content || '', 220);
    body.appendChild(text);

    body.appendChild(metaRow(p));

    if (postOpts.show_share_icons) {
      var sh = shareBar(p.video_url);
      if (sh) body.appendChild(sh);
    }

    a.appendChild(body);
    return a;
  }

  function applyLayoutExtras(inner, cards) {
    if (feedStyle === 'stagger') {
      cards.forEach(function (card, i) {
        card.style.animationDelay = i * 0.07 + 's';
      });
    }
    if (feedStyle === 'layers') {
      inner.style.position = 'relative';
      inner.style.minHeight = Math.min(520, 140 + cards.length * 26) + 'px';
      var pw = inner.parentElement ? inner.parentElement.clientWidth : 400;
      var baseW = Math.min(360, pw * 0.88);
      cards.forEach(function (card, i) {
        card.style.position = 'absolute';
        card.style.width = baseW + 'px';
        card.style.left = '50%';
        card.style.marginLeft = -baseW / 2 + i * 14 + 'px';
        card.style.top = 24 + i * 20 + 'px';
        card.style.zIndex = String(10 + i);
      });
    }
  }

  function fetchPage(offset) {
    var sep = POSTS_URL.indexOf('?') >= 0 ? '&' : '?';
    return fetch(POSTS_URL + sep + 'limit=' + perPage + '&offset=' + offset, {
      credentials: 'omit',
    }).then(function (r) {
      return r.json();
    });
  }

  containers.forEach(function (container) {
    applyColorVars(container);
    var inner = ensureInner(container);
    if (feedStyle === 'showcase_carousel') {
      wrapShowcaseViewport(container, inner);
    }
    var state = {
      offset: 0,
      hasMore: true,
      loading: false,
      io: null,
    };

    function appendPosts(posts) {
      var start = inner.querySelectorAll('.crt-card').length;
      posts.forEach(function (p, j) {
        inner.appendChild(renderCard(p, start + j, p.video_url));
      });
      var cards = Array.prototype.slice.call(inner.querySelectorAll('.crt-card'));
      applyLayoutExtras(inner, cards);
    }

    state.loadNext = function () {
      if (!state.hasMore || state.loading) return;
      state.loading = true;
      var prevOffset = state.offset;
      fetchPage(prevOffset)
        .then(function (data) {
          var posts = (data && data.posts) || [];
          state.loading = false;
          if (posts.length === 0) {
            state.hasMore = false;
          } else {
            appendPosts(posts);
            state.offset += posts.length;
            var meta = data.meta || {};
            if (meta.has_more === false) state.hasMore = false;
            if (posts.length < perPage) state.hasMore = false;
          }

          if (!lazyLoad && showLoadMore) {
            var rowBtn = container.querySelector('.crt-load-more');
            if (rowBtn) {
              rowBtn.disabled = state.loading;
              rowBtn.textContent = state.loading ? 'Loading…' : 'Load more';
            }
          }

          addLoadRow(container, state);
          if (!state.hasMore && state.io) {
            try {
              state.io.disconnect();
            } catch (e) {}
            state.io = null;
          }
        })
        .catch(function () {
          state.loading = false;
          state.hasMore = false;
          inner.innerHTML =
            '<div class="crt-error" style="color:var(--crt-date);font-size:13px;">Failed to load feed.</div>';
        });
    };

    state.loadNext();
  });
})();
