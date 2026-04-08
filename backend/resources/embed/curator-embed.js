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

  var perPage = Math.max(1, Math.min(parseInt(feedOpts.posts_per_page, 10) || 12, 100));
  var postMin = Math.max(120, Math.min(parseInt(feedOpts.post_min_width, 10) || 260, 600));
  var lazyLoad = feedOpts.lazy_load !== false;
  var showLoadMore = feedOpts.show_load_more !== false;

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

  function youtubeId(url) {
    var m = String(url || '').match(
      /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([^&?/]+)/,
    );
    return m ? m[1] : null;
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
    var enc = encodeURIComponent(url);
    var bar = document.createElement('div');
    bar.className = 'crt-share';
    var links = [
      { h: 'https://twitter.com/intent/tweet?url=' + enc, t: '𝕏' },
      { h: 'https://www.facebook.com/sharer/sharer.php?u=' + enc, t: 'f' },
      { h: 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc, t: 'in' },
    ];
    links.forEach(function (L) {
      var a = document.createElement('a');
      a.href = L.h;
      a.target = '_blank';
      a.rel = 'noreferrer';
      a.className = 'crt-share-link';
      a.textContent = L.t;
      a.title = 'Share';
      bar.appendChild(a);
    });
    return bar;
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

  function renderCard(p, index, linkHref) {
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
