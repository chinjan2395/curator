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
