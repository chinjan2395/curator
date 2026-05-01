<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\PublishSettings;
use Illuminate\Http\Request;

class EmbedController extends Controller
{
    public function css(string $publicKey)
    {
        Workspace::query()->where('public_key', $publicKey)->firstOrFail();

        $css = <<<'CSS'
.crt-wrap{
  font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji";
  color:var(--crt-text,#0f172a);
  background:transparent;
}
.crt-inner{min-height:24px;}
.crt-card{
  border:1px solid var(--crt-border,#e2e8f0);
  border-radius:12px;
  background:var(--crt-card-bg,#ffffff);
  overflow:hidden;
  box-shadow:0 1px 3px rgba(0,0,0,.06);
  box-sizing:border-box;
}
.crt-media{aspect-ratio:16/9;background:#f1f5f9;display:block;position:relative;overflow:hidden;}
.crt-media img{width:100%;height:100%;object-fit:cover;display:block;}
.crt-yt{width:100%;aspect-ratio:16/9;border:0;display:block;}
.crt-media-ph{display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--crt-date,#64748b);}
.crt-body{padding:10px 12px;}
.crt-source-row{
  display:flex;
  margin-bottom:10px;
  gap:8px;
  color:var(--crt-date,#64748b);
}
.crt-source-row--stacked{flex-direction:column;}
.crt-source-row--inline{
  flex-direction:row;
  align-items:center;
}
.crt-source-row--align-center.crt-source-row--stacked{
  align-items:center;
  text-align:center;
}
.crt-source-row--align-start.crt-source-row--stacked{
  align-items:flex-start;
  text-align:left;
}
.crt-source-row--align-center.crt-source-row--inline{justify-content:center;}
.crt-source-row--align-start.crt-source-row--inline{justify-content:flex-start;}
.crt-source-label{
  font-size:12px;
  font-weight:600;
  letter-spacing:.02em;
  line-height:1.25;
  word-break:break-word;
}
.crt-platform-badge--inline{
  display:inline-flex;
  flex-shrink:0;
}
.crt-platform-badge--inline svg{display:block;}
.crt-title{font-size:14px;font-weight:600;line-height:1.3;margin:0 0 6px 0;color:var(--crt-text,#0f172a);}
.crt-text{font-size:13px;line-height:1.35;color:var(--crt-text,#0f172a);opacity:.92;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.crt-meta{margin-top:10px;font-size:12px;color:var(--crt-date,#64748b);display:flex;justify-content:space-between;gap:8px;align-items:center;}
.crt-meta-social{color:var(--crt-icon,#64748b);}
.crt-link{color:inherit;text-decoration:none;cursor:pointer;}
.crt-link:hover .crt-title{color:var(--crt-link,#2563eb);text-decoration:underline;text-underline-offset:3px;}
.crt-share{display:flex;gap:8px;margin-top:10px;}
.crt-share-link{
  display:inline-flex;align-items:center;justify-content:center;
  width:28px;height:28px;border-radius:8px;
  border:1px solid var(--crt-border,#e2e8f0);
  font-size:11px;font-weight:700;color:var(--crt-icon,#64748b);
  text-decoration:none;background:rgba(255,255,255,.6);
}
.crt-share-link:hover{color:var(--crt-link,#2563eb);border-color:var(--crt-link,#2563eb);}
.crt-load-row{width:100%;margin-top:16px;display:flex;justify-content:center;}
.crt-load-more{
  padding:8px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;
  border:1px solid var(--crt-border,#e2e8f0);
  background:var(--crt-card-bg,#fff);color:var(--crt-btn,#0f172a);
}
.crt-load-more:hover{opacity:.92;}
.crt-load-more:disabled{opacity:.55;cursor:not-allowed;}
.crt-lazy-sentinel{width:100%;height:2px;flex-shrink:0;pointer-events:none;}

/* —— layouts —— */
.crt-inner.crt-layout--grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(var(--crt-post-min,260px),1fr));
  gap:12px;
}
.crt-inner.crt-layout--waterfall{
  column-count:3;
  column-gap:12px;
}
@media (max-width:900px){ .crt-inner.crt-layout--waterfall{ column-count:2; } }
@media (max-width:640px){ .crt-inner.crt-layout--waterfall{ column-count:1; } }
.crt-inner.crt-layout--waterfall .crt-card{ break-inside:avoid; margin-bottom:12px; }

.crt-inner.crt-layout--list{
  display:flex;
  flex-direction:column;
  gap:12px;
}
.crt-inner.crt-layout--list .crt-card{
  display:flex;
  flex-direction:row;
  align-items:stretch;
  max-width:100%;
}
.crt-inner.crt-layout--list .crt-media{flex:0 0 200px;max-width:42%;aspect-ratio:16/9;}
.crt-inner.crt-layout--list .crt-body{flex:1;}

.crt-inner.crt-layout--carousel{
  display:flex;
  flex-direction:row;
  gap:12px;
  overflow-x:auto;
  scroll-snap-type:x mandatory;
  padding-bottom:8px;
}
.crt-inner.crt-layout--carousel .crt-card{
  flex:0 0 min(320px,85vw);
  scroll-snap-align:start;
}

.crt-inner.crt-layout--grid_carousel{
  display:grid;
  grid-auto-flow:column;
  grid-auto-columns:minmax(var(--crt-post-min,260px),320px);
  overflow-x:auto;
  gap:12px;
  padding-bottom:8px;
  scroll-snap-type:x mandatory;
}
.crt-inner.crt-layout--grid_carousel .crt-card{ scroll-snap-align:start; }

.crt-wrap.crt-wrap--showcase{
  background:var(--crt-showcase-shell-bg,#0a0a0a);
  border-radius:14px;
  padding:10px 0 14px;
}
.crt-showcase-viewport{
  position:relative;
  width:100%;
}
.crt-showcase-nav{
  position:absolute;
  top:50%;
  transform:translateY(-50%);
  z-index:4;
  width:44px;height:44px;
  border:none;border-radius:999px;
  background:rgba(255,255,255,.14);
  color:#fff;font-size:26px;line-height:1;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 12px rgba(0,0,0,.35);
  transition:background .2s ease,transform .15s ease;
}
.crt-showcase-nav:hover{background:rgba(255,255,255,.24);}
.crt-showcase-nav:active{transform:translateY(-50%) scale(.96);}
.crt-showcase-nav--prev{left:6px;}
.crt-showcase-nav--next{right:6px;}

.crt-inner.crt-layout--showcase_carousel{
  display:flex;
  flex-direction:row;
  gap:14px;
  overflow-x:auto;
  scroll-snap-type:x mandatory;
  scroll-padding:0 52px;
  padding:6px 52px 12px;
  scrollbar-width:thin;
  scrollbar-color:rgba(255,255,255,.25) transparent;
}
.crt-inner.crt-layout--showcase_carousel::-webkit-scrollbar{height:6px;}
.crt-inner.crt-layout--showcase_carousel::-webkit-scrollbar-thumb{
  background:rgba(255,255,255,.22);border-radius:99px;
}

.crt-card.crt-card--showcase{
  flex:0 0 min(280px,calc(85vw - 40px));
  max-width:300px;
  scroll-snap-align:start;
  display:flex;
  flex-direction:column;
  border-radius:12px;
  border:1px solid rgba(255,255,255,.1);
  box-shadow:0 8px 28px rgba(0,0,0,.45);
}
.crt-card.crt-card--showcase .crt-media{
  aspect-ratio:3/4;
  background:#111;
}
.crt-card.crt-card--showcase .crt-media img{
  object-fit:cover;
}
.crt-media-overlay{
  position:absolute;inset:0;
  display:flex;
  pointer-events:none;
  background:linear-gradient(180deg,rgba(0,0,0,.05) 40%,rgba(0,0,0,.45) 100%);
}
.crt-media-overlay--center{align-items:center;justify-content:center;}
.crt-media-overlay--top-left{align-items:flex-start;justify-content:flex-start;padding:12px;}
.crt-media-overlay--top-right{align-items:flex-start;justify-content:flex-end;padding:12px;}
.crt-media-overlay--bottom-left{align-items:flex-end;justify-content:flex-start;padding:12px;}
.crt-media-overlay--bottom-right{align-items:flex-end;justify-content:flex-end;padding:12px;}
.crt-brand-img--media{
  max-height:76px;max-width:96px;width:auto;height:auto;
  object-fit:contain;display:block;
  filter:drop-shadow(0 2px 10px rgba(0,0,0,.5));
}
.crt-brand-img--inline{width:18px;height:18px;object-fit:contain;display:block;border-radius:4px;}
.crt-showcase-provider-icon svg{display:block;}
.crt-showcase-provider-icon--inline svg{width:18px;height:18px;display:block;border-radius:4px;}

.crt-body.crt-body--showcase{
  flex:1;
  display:flex;
  flex-direction:column;
  padding:12px 14px 14px;
  min-height:0;
}
.crt-showcase-source{
  display:flex;
  align-items:center;
  gap:8px;
  margin-bottom:8px;
  font-size:11px;
  letter-spacing:.05em;
  text-transform:uppercase;
  color:var(--crt-date,#94a3b8);
}
.crt-showcase-source-icon svg{width:18px;height:18px;display:block;border-radius:4px;}
.crt-showcase-source-icon .crt-brand-img--inline{border-radius:4px;}
.crt-showcase-feed-name{
  font-weight:700;
  color:var(--crt-date,#94a3b8);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  max-width:100%;
}
.crt-title.crt-title--showcase{
  font-size:15px;
  font-weight:700;
  line-height:1.25;
  margin:0 0 8px;
}
.crt-text.crt-text--showcase{
  -webkit-line-clamp:4;
  opacity:.95;
  flex:1;
}
.crt-hashtags{
  display:flex;
  flex-wrap:wrap;
  gap:6px 10px;
  margin-top:8px;
}
.crt-hashtag{
  font-size:12px;
  font-weight:600;
  color:var(--crt-link,#38bdf8);
}
.crt-showcase-footer{
  margin-top:auto;
  padding-top:12px;
  display:flex;
  align-items:center;
  gap:10px;
  border-top:1px solid rgba(255,255,255,.08);
}
.crt-showcase-avatar{
  flex-shrink:0;
  width:32px;height:32px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;
  color:#fff;
  background:linear-gradient(135deg,#475569,#1e293b);
}
.crt-showcase-avatar--img{
  display:block;padding:0;
  object-fit:cover;
  background:transparent;
}
.crt-showcase-meta-stack{
  flex:1;
  min-width:0;
  display:flex;
  flex-direction:column;
  gap:2px;
}
.crt-showcase-handle{
  font-size:11px;
  font-weight:800;
  letter-spacing:.06em;
  color:var(--crt-text,#f8fafc);
}
.crt-showcase-foot-date{
  font-size:11px;
  color:var(--crt-date,#94a3b8);
}
.crt-showcase-share-btn{
  flex-shrink:0;
  width:36px;height:36px;
  border-radius:10px;
  border:1px solid rgba(255,255,255,.14);
  background:rgba(255,255,255,.04);
  color:var(--crt-link,#38bdf8);
  display:flex;
  align-items:center;
  justify-content:center;
  text-decoration:none;
  cursor:pointer;
  transition:border-color .15s ease,background .15s ease;
}
.crt-showcase-share-btn:hover{
  border-color:var(--crt-link,#38bdf8);
  background:rgba(56,189,248,.08);
}
.crt-card.crt-card--showcase.crt-link:hover .crt-title{color:var(--crt-link,#38bdf8);}

.crt-inner.crt-layout--mosaic{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  grid-auto-rows:100px;
  gap:10px;
}
@media (max-width:900px){ .crt-inner.crt-layout--mosaic{ grid-template-columns:repeat(2,1fr); } }
.crt-inner.crt-layout--mosaic .crt-card:nth-child(6n+1){ grid-column:span 2; grid-row:span 2; }
.crt-inner.crt-layout--mosaic .crt-card:nth-child(6n+4){ grid-column:span 2; }

.crt-inner.crt-layout--tetris{
  display:grid;
  grid-template-columns:repeat(6,1fr);
  grid-auto-rows:88px;
  gap:8px;
}
@media (max-width:900px){
  .crt-inner.crt-layout--tetris{ grid-template-columns:repeat(3,1fr); }
  .crt-inner.crt-layout--tetris .crt-card:nth-child(odd){ grid-column:span 2; }
}

.crt-inner.crt-layout--select{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:12px;
}
@media (max-width:900px){ .crt-inner.crt-layout--select{ grid-template-columns:repeat(2,1fr); } }
.crt-inner.crt-layout--select .crt-card:first-child{ grid-column:1/-1; }
@media (min-width:901px){
  .crt-inner.crt-layout--select .crt-card:first-child{ grid-column:span 2; grid-row:span 2; }
}

.crt-inner.crt-layout--cover_flow{
  display:flex;
  perspective:1200px;
  gap:10px;
  overflow-x:auto;
  padding:28px 12px;
  transform-style:preserve-3d;
}
.crt-inner.crt-layout--cover_flow .crt-card{
  flex:0 0 240px;
  transform:rotateY(38deg) scale(.9);
  opacity:.92;
  transition:transform .25s ease,opacity .25s ease;
}
.crt-inner.crt-layout--cover_flow .crt-card:hover{
  transform:rotateY(0) scale(1);
  opacity:1;
  z-index:3;
}

.crt-inner.crt-layout--stagger{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(var(--crt-post-min,260px),1fr));
  gap:12px;
}
@keyframes crtFadeUp{
  from{ opacity:0; transform:translateY(14px); }
  to{ opacity:1; transform:translateY(0); }
}
.crt-inner.crt-layout--stagger .crt-card{
  animation:crtFadeUp .55s ease backwards;
}

.crt-inner.crt-layout--layers .crt-card{ cursor:pointer; }
CSS;

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function js(Request $request, string $publicKey)
    {
        $workspace = Workspace::query()->where('public_key', $publicKey)->firstOrFail();

        $base = rtrim((string) config('app.url', ''), '/');
        $postsUrl = $base.'/api/public/feeds/'.$workspace->public_key.'/posts';

        $settingsJson = json_encode(
            PublishSettings::merge($workspace->publish_settings),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $path = resource_path('embed/curator-embed.js');
        $runtime = is_readable($path) ? (string) file_get_contents($path) : '';

        if ($runtime === '') {
            $runtime = 'console.error("Curator embed runtime missing");';
        }

        $bootstrap = 'var CRT_POSTS_URL = '.$this->jsString($postsUrl).";\n"
            .'var CRT_PUBLIC_KEY = '.$this->jsString($workspace->public_key).";\n"
            .'var CRT_SETTINGS = '.$settingsJson.";\n";

        return response($bootstrap."\n".$runtime, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
