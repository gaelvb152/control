<?php
require_once __DIR__ . '/security_headers.php';
header('Cache-Control: no-cache, must-revalidate');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Control de bandas</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f6f7f8; --panel:#ffffff; --ink:#182430; --muted:#7d8a96; --line:#e7eaed; --soft:#f1f3f4;
  --accent:#e3a72e; --accent-ink:#1c1500;
  --ok:#1d8a5c; --ok-bg:#e6f5ee; --bad:#c23b32; --bad-bg:#fbeae8; --warn:#8a6300; --warn-bg:#fdf3d9;
  --info:#2564a8; --info-bg:#e6eefa; --bronco:#8a4420; --bronco-bg:#f7ece3; --maverick:#256a90; --maverick-bg:#e3f0f7;
  --bar:#ffffff; --bar-ink:#182430;
  --shadow:0 1px 2px rgba(20,30,40,.05),0 1px 1px rgba(20,30,40,.04);
  --font-head:'Barlow Semi Condensed','Arial Narrow',system-ui,sans-serif;
  --font-body:'Barlow',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  box-sizing:border-box;
  padding-top:env(safe-area-inset-top,0px);
  padding-bottom:env(safe-area-inset-bottom,0px);
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --bg:#0f151b; --panel:#171f27; --ink:#e7edf1; --muted:#8d9caa; --line:#242f39; --soft:#1b232b;
    --ok:#4cc394; --ok-bg:#122e24; --bad:#ff9086; --bad-bg:#351b19; --warn:#f0c655; --warn-bg:#33290c;
    --info:#7fb4ec; --info-bg:#142640; --bronco:#eda173; --bronco-bg:#331f13; --maverick:#7cc0e4; --maverick-bg:#102a37;
    --bar:#0d1319; --bar-ink:#e7edf1;
    --shadow:0 1px 2px rgba(0,0,0,.4),0 1px 1px rgba(0,0,0,.3);
  }
}
html{scroll-padding-top:env(safe-area-inset-top,0px)}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.45 var(--font-body);-webkit-text-size-adjust:100%}
button,input,select,textarea{font:inherit;color:inherit}
h1,h2,h3{font-family:var(--font-head);margin:0;font-weight:700;letter-spacing:.01em}
.wrap{max-width:1120px;margin:0 auto;padding:20px 16px}
.wrap.narrow{max-width:520px}
.top{background:var(--bar);color:var(--bar-ink);border-bottom:1px solid var(--line)}
.top-in{max-width:1120px;margin:0 auto;padding:14px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.brand{font-family:var(--font-head);font-weight:700;font-size:20px;letter-spacing:.01em;margin-right:auto}
.brand small{display:block;font:500 12.5px var(--font-body);color:var(--muted);letter-spacing:0}
.strap{height:3px;background:var(--accent)}
.btn{border:1px solid var(--line);background:var(--panel);border-radius:10px;padding:9px 16px;cursor:pointer;font-weight:600;min-height:40px;transition:border-color .15s,background .15s}
.btn:hover{border-color:var(--muted);background:var(--soft)}
.btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
.btn.primary:hover{filter:brightness(.96)}
.btn.ok{background:var(--ok);border-color:var(--ok);color:#fff}
.btn.danger{color:var(--bad);border-color:var(--bad-bg)}
.btn.danger:hover{background:var(--bad-bg)}
.btn.big{width:100%;min-height:56px;font-size:18px;font-family:var(--font-head);letter-spacing:.02em}
.btn.icon{min-width:40px;padding:8px 10px;font-size:20px;line-height:1}
.btn:disabled{opacity:.4;cursor:not-allowed}
.btn.sm{min-height:32px;padding:5px 12px;font-size:13.5px;border-radius:8px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:14px;box-shadow:var(--shadow)}
.lbl{font-size:12.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.02em}
.sub{color:var(--muted);font-size:14px}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.row.sp{justify-content:space-between}
.stack>*+*{margin-top:12px}
input[type=text],input[type=password],input[type=number],input[type=date],select,textarea{width:100%;background:var(--panel);border:1px solid var(--line);border-radius:9px;padding:9px 11px;min-height:42px}
input:focus,select:focus,textarea:focus{outline:2px solid var(--accent);outline-offset:1px}
textarea{min-height:150px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px}
input.num{font-family:var(--font-head);font-weight:700;font-size:32px;text-align:center;min-height:64px}
label.f{display:block}
label.f>span{display:block;font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:4px}
.chip{display:inline-block;padding:2px 9px;border-radius:999px;font-weight:600;font-size:13.5px;white-space:nowrap}
.chip.bronco{background:var(--bronco-bg);color:var(--bronco)}
.chip.maverick{background:var(--maverick-bg);color:var(--maverick)}
.tag{display:inline-block;padding:3px 10px;border-radius:7px;font-size:12.5px;font-weight:700;white-space:nowrap}
.tag.neutral{background:var(--soft);color:var(--muted)}
.tag.warn{background:var(--warn-bg);color:var(--warn)}
.tag.info{background:var(--info-bg);color:var(--info)}
.tag.ok{background:var(--ok-bg);color:var(--ok)}
.tag.bad{background:var(--bad-bg);color:var(--bad)}
.trip-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px}
.nod{font-family:var(--font-head);font-size:32px;font-weight:700;line-height:1}
.need{margin:8px 0 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.totals{display:grid;grid-template-columns:1fr auto;gap:4px 12px;align-items:end;background:var(--soft);border-radius:10px;padding:12px 14px;margin-bottom:12px}
.big-n{font-family:var(--font-head);font-size:52px;font-weight:700;line-height:1}
.dif{font-family:var(--font-head);font-size:20px;font-weight:700;text-align:right}
.dif.neg{color:var(--bad)} .dif.pos{color:var(--info)} .dif.zero{color:var(--ok)}
.list{list-style:none;margin:0;padding:0}
.list li{padding:8px 0;border-top:1px solid var(--line);font-size:14.5px}
.note{border-radius:10px;padding:10px 12px;font-size:14.5px}
.note.warn{background:var(--warn-bg);color:var(--warn)}
.note.info{background:var(--info-bg);color:var(--info)}
.note.ok{background:var(--ok-bg);color:var(--ok)}
.note.bad{background:var(--bad-bg);color:var(--bad)}
.sigimg{max-width:320px;width:100%;background:#fff;border:1px solid var(--line);border-radius:8px;display:block}
.sigimg.ph{padding:20px;color:#555;font-size:14px;text-align:center}
.photo{display:block;max-width:100%;max-height:360px;border-radius:10px;border:1px solid var(--line);margin:8px 0;background:var(--soft)}
.notifs .note{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:8px}
.steps{display:flex;gap:6px;margin:0 0 14px}
.step{flex:1;text-align:center;font:700 13px var(--font-head);padding:7px 4px;border-radius:8px;background:var(--soft);color:var(--muted);letter-spacing:.02em}
.step.done{background:var(--ok-bg);color:var(--ok)}
.step.now{background:var(--accent);color:var(--accent-ink)}
.tabs{display:flex;gap:2px;border-bottom:1px solid var(--line);margin-bottom:16px;overflow-x:auto}
.tab{background:none;border:none;padding:10px 16px;font:700 15.5px var(--font-head);color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap}
.tab[aria-selected=true]{color:var(--ink);border-bottom-color:var(--accent)}
.badge{display:inline-block;background:var(--bad);color:#fff;border-radius:999px;font-size:11.5px;padding:1px 7px;margin-left:6px;vertical-align:2px}
.datebar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
.datebar input[type=date]{width:auto}
.seg{display:flex;gap:6px;flex-wrap:wrap}
.seg .btn{padding:5px 12px;min-height:32px;font-size:13.5px}
.seg .btn[aria-pressed=true]{background:var(--ink);color:var(--bg);border-color:var(--ink)}
.tablewrap{overflow-x:auto;background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);margin-bottom:14px}
table{border-collapse:collapse;width:100%;min-width:760px}
th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);font-size:14.5px;vertical-align:middle}
th{font:700 12px var(--font-body);color:var(--muted);background:var(--soft);text-transform:uppercase;letter-spacing:.03em}
tbody tr.click{cursor:pointer}
tbody tr.click:hover{background:var(--soft)}
tr.stale td{background:var(--warn-bg)}
td.n,th.n{text-align:right;font-family:var(--font-head);font-weight:700;font-size:17px}
th.n{font:700 12px var(--font-body)}
.empty{text-align:center;padding:36px 16px;color:var(--muted)}
.empty h3{color:var(--ink);margin-bottom:6px}
.req-card{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
.kpi{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:12px 14px;box-shadow:var(--shadow)}
.kpi span{display:block;font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.02em}
.kpi b{font-family:var(--font-head);font-size:30px;line-height:1.15}
.kpi small{display:block;color:var(--muted);font-size:12.5px}
.overlay{position:fixed;inset:0;background:rgba(5,10,14,.55);display:flex;align-items:center;justify-content:center;z-index:20;padding:max(12px,env(safe-area-inset-top,0px)) 12px max(12px,env(safe-area-inset-bottom,0px))}
.modal{background:var(--panel);border-radius:16px;width:100%;max-width:760px;max-height:100%;display:flex;flex-direction:column;border:1px solid var(--line)}
.mhead{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;padding:14px 16px;border-bottom:1px solid var(--line)}
.mbody{padding:16px;overflow-y:auto}
.kvs{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
.kv{background:var(--soft);border-radius:9px;padding:8px 10px}
.kv span{display:block;font-size:12px;color:var(--muted);font-weight:600}
.kv b{font-family:var(--font-head);font-size:19px}
.sect{border-top:1px solid var(--line);margin-top:16px;padding-top:14px}
.sect h3{font-size:17px;margin-bottom:8px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media (max-width:560px){.grid2{grid-template-columns:1fr}.big-n{font-size:44px}.overlay{align-items:flex-end;padding:0}.modal{border-radius:16px 16px 0 0;max-height:94%}}
.toast{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(18px + env(safe-area-inset-bottom,0px));background:var(--ink);color:var(--bg);padding:10px 16px;border-radius:10px;z-index:40;font-weight:600;max-width:90%;box-shadow:0 4px 16px rgba(0,0,0,.2)}
.loading{padding:60px 16px;text-align:center;color:var(--muted)}

/* ---- Pantalla de firma a pantalla completa ---- */
.signfs{position:fixed;inset:0;z-index:100;background:var(--bg);display:flex;flex-direction:column;overscroll-behavior:contain;
  padding:env(safe-area-inset-top,0px) env(safe-area-inset-right,0px) env(safe-area-inset-bottom,0px) env(safe-area-inset-left,0px)}
.signfs-top{padding:8px 14px;display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid var(--line);background:var(--panel)}
.signfs-top .t1{font:700 22px var(--font-head)}
.signfs-top .t2{font-size:13.5px;color:var(--muted)}
.signfs-top .big{font:700 38px var(--font-head);line-height:1;text-align:right}
.signfs-pad{flex:1;position:relative;margin:6px;background:#fff;border:2px dashed #9aa5af;border-radius:14px;min-height:0;overflow:hidden}
.signfs-pad canvas{position:absolute;inset:0;width:100%;height:100%;touch-action:none;cursor:crosshair;display:block}
.signfs-hint{position:absolute;left:0;right:0;top:42%;text-align:center;color:#c1c8ce;font:600 28px var(--font-head);pointer-events:none;letter-spacing:.04em}
.signfs-line{position:absolute;left:5%;right:5%;bottom:20%;border-bottom:2px solid #dde1e5;pointer-events:none}
.signfs-err{color:var(--bad);background:var(--bad-bg);padding:8px 14px;font-weight:600;font-size:15px}
.signfs-bar{display:flex;gap:10px;padding:8px 10px;background:var(--panel);border-top:1px solid var(--line)}
.signfs-bar .btn{min-height:54px;font-size:19px;font-family:var(--font-head);letter-spacing:.02em}
.signfs-bar .grow{flex:1}
@media (orientation:landscape) and (max-height:520px){
  .signfs-top{padding:6px 14px}.signfs-top .big{font-size:30px}.signfs-bar{padding:8px 14px}.signfs-bar .btn{min-height:50px}.signfs-pad{margin:8px 10px}
}

/* ---- v2: rol, calendario, rankings, vista previa, impresión ---- */
.brand{display:flex;align-items:center;gap:10px}
.brand-mark{width:34px;height:34px;border-radius:9px;background:var(--accent);color:var(--accent-ink);display:grid;place-items:center;font:700 17px var(--font-head);flex:none}
.brand-txt{line-height:1.15}
.pill{display:inline-block;padding:1px 9px;border-radius:999px;font:600 12px var(--font-body);background:var(--soft);color:var(--muted);border:1px solid var(--line);vertical-align:1px;margin-left:6px}
.pill.admin{background:var(--warn-bg);color:var(--warn);border-color:transparent}
.pill.administrativo{background:var(--info-bg);color:var(--info);border-color:transparent}
.sec-title{display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap;margin:18px 0 8px}
.sec-title h3{font-size:19px}
.rank{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);margin-bottom:14px;overflow:hidden}
.rank-row{display:grid;grid-template-columns:28px minmax(120px,1.3fr) minmax(90px,2fr) auto;gap:10px;align-items:center;padding:9px 14px;border-top:1px solid var(--line);font-size:14.5px}
.rank-row:first-child{border-top:none}
.rank-row .pos{font:700 15px var(--font-head);color:var(--muted);text-align:center}
.rank-row .who b{display:block;line-height:1.2}
.rank-row .who small{color:var(--muted);font-size:12.5px}
.rank-row .val{font:700 20px var(--font-head);text-align:right;min-width:48px}
.rank-row .val small{display:block;font:500 11.5px var(--font-body);color:var(--muted)}
.bar{height:10px;background:var(--soft);border-radius:999px;overflow:hidden;display:flex}
.bar i{display:block;height:100%;background:var(--accent)}
.bar i.b2{background:var(--info)}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:var(--muted);margin:0 0 8px}
.legend span:before{content:"";display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--accent);margin-right:6px;vertical-align:-1px}
.legend span.l2:before{background:var(--info)}
.legend.plain span:before{display:none}
.cap:first-letter{text-transform:uppercase}
.cal{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:14px}
.cal-head,.cal-grid{display:grid;grid-template-columns:repeat(7,1fr)}
.cal-head div{padding:8px 4px;text-align:center;font:700 12px var(--font-body);color:var(--muted);text-transform:uppercase;letter-spacing:.04em;background:var(--soft);border-bottom:1px solid var(--line)}
.cal-cell{min-height:84px;padding:6px 7px;border-right:1px solid var(--line);border-bottom:1px solid var(--line);background:var(--panel);text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:3px;border-top:none;border-left:none;border-radius:0}
.cal-cell:nth-child(7n){border-right:none}
.cal-cell:hover{background:var(--soft)}
.cal-cell.out{background:var(--bg);cursor:default;opacity:.55}
.cal-cell.today .dn{background:var(--accent);color:var(--accent-ink);border-radius:999px}
.cal-cell.sel{outline:2px solid var(--accent);outline-offset:-2px}
.cal-cell .dn{font:700 13px var(--font-head);width:24px;height:24px;display:grid;place-items:center}
.cal-cell .m1{font:700 17px var(--font-head);line-height:1}
.cal-cell .m1 small{font:500 11px var(--font-body);color:var(--muted)}
.cal-cell .m2{font-size:11.5px;color:var(--muted);line-height:1.25}
.cal-cell .dots{display:flex;gap:4px;flex-wrap:wrap}
.dot{display:inline-block;padding:0 6px;border-radius:999px;font:700 11px var(--font-body);line-height:17px}
.dot.warn{background:var(--warn-bg);color:var(--warn)}.dot.bad{background:var(--bad-bg);color:var(--bad)}.dot.ok{background:var(--ok-bg);color:var(--ok)}.dot.info{background:var(--info-bg);color:var(--info)}
@media (max-width:560px){.cal-cell{min-height:62px;padding:4px}.cal-cell .m2{display:none}.rank-row{grid-template-columns:22px 1fr auto;}.rank-row .bar{grid-column:2/4;grid-row:2}.cal-head div{font-size:10.5px}}
.pv{border:1px solid var(--line);border-radius:12px;overflow:hidden;margin:2px 0}
.pv-row{padding:10px 12px;border-top:1px solid var(--line);display:grid;gap:8px}
.pv-row:first-child{border-top:none}
.pv-row.error{background:var(--bad-bg)}.pv-row.revisar{background:var(--warn-bg)}
.pv-row .pv-top{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:14px}
.pv-row .pv-top b{font-family:var(--font-head);font-size:17px}
.pv-row .pv-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px}
@media (max-width:560px){.pv-row .pv-grid{grid-template-columns:1fr}}
.pv-row .avisos{font-size:13px;color:var(--ink);opacity:.85}
.pv-row.skip{opacity:.5}
.chk{display:flex;gap:8px;align-items:center;font-size:13.5px}
.chk input{width:18px;height:18px}
.reveal{display:flex;gap:6px}
.reveal input{flex:1}
.comment{border-left:3px solid var(--accent);background:var(--soft);padding:8px 12px;border-radius:0 9px 9px 0;font-size:14.5px;margin:6px 0}
.comment small{display:block;color:var(--muted);font-size:12.5px}
.sigbox{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.userrow input{min-height:34px;padding:5px 8px}
.only-print{display:none}
@media print{
  body{background:#fff;color:#000}.top,.tabs,.btn,.datebar,.toast,.no-print,input,select,textarea,.overlay{display:none!important}
  .only-print{display:block}.wrap{max-width:none;padding:0}.card,.tablewrap,.rank,.cal,.kpi{box-shadow:none;break-inside:avoid}
  .cal-cell{min-height:60px}
}

/* ---- v3: operador, inventario, panel (diseño mejorado) ---- */
.hero{display:flex;align-items:center;gap:14px;margin:2px 0 14px}
.avatar{width:50px;height:50px;border-radius:50%;background:var(--accent);color:var(--accent-ink);display:grid;place-items:center;font:700 20px var(--font-head);flex:none;letter-spacing:.02em}
.hero h2{font-size:23px;line-height:1.1;overflow-wrap:anywhere}
.hero .sub{margin-top:3px}
.hero-sum{margin-left:auto;text-align:right;flex:none}
.hero-sum b{display:block;font:700 26px/1 var(--font-head)}
.hero-sum span{font-size:12px;color:var(--muted);font-weight:600}
.datebar{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:8px;box-shadow:var(--shadow)}
.datebar .sub{margin-left:auto;padding:0 6px}
.tcard{padding:0;overflow:hidden}
.tcard-top{padding:16px 18px 14px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,var(--soft),var(--panel))}
.tcard-main{padding:16px 18px 18px}
.tcard .trip-head{margin-bottom:12px}
.tcard .nod{font-size:38px;letter-spacing:.01em}
.tcard .nod-lbl{font:600 12px var(--font-body);color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
.need{margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.need .chip{font-size:15px;padding:4px 12px}
.need-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.meta{display:inline-flex;align-items:center;gap:6px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:3px 10px;font-size:13.5px;color:var(--muted)}
.meta b{color:var(--ink);font-family:var(--font-head);font-size:15px}
.steps{position:relative;display:flex;gap:0;margin:0 0 16px;justify-content:space-between}
.steps:before{content:"";position:absolute;left:12%;right:12%;top:15px;height:2px;background:var(--line)}
.step{position:relative;flex:1;background:none;padding:0;text-align:center;font:600 12.5px var(--font-body);color:var(--muted);letter-spacing:0}
.step:before{content:attr(data-n);display:grid;place-items:center;width:30px;height:30px;margin:0 auto 5px;border-radius:50%;background:var(--panel);border:2px solid var(--line);font:700 14px var(--font-head);position:relative}
.step.done{background:none;color:var(--ok)}
.step.done:before{content:"";background:var(--ok) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.2' stroke-linecap='round' stroke-linejoin='round'><path d='M5 12.5l4.5 4.5L19 7.5'/></svg>") center/17px no-repeat;border-color:var(--ok)}
.step.now{background:none;color:var(--ink);font-weight:700}
.step.now:before{background:var(--accent);border-color:var(--accent);color:var(--accent-ink);box-shadow:0 0 0 4px rgba(227,167,46,.25)}
.totals{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
.totals .prog{grid-column:1/-1;margin-top:8px}
.prog{height:8px;border-radius:999px;background:var(--line);overflow:hidden}
.prog i{display:block;height:100%;border-radius:999px;background:var(--ok);transition:width .3s}
.prog.low i{background:var(--accent)}
.lvl .prog i{background:var(--ok)}.lvl .prog.low i{background:var(--accent)}
.prog-l{display:flex;justify-content:space-between;font-size:12.5px;color:var(--muted);margin-top:4px;grid-column:1/-1}
.tl{list-style:none;margin:0 0 14px;padding:0 0 0 18px;position:relative}
.tl:before{content:"";position:absolute;left:5px;top:8px;bottom:8px;width:2px;background:var(--line)}
.tl li{position:relative;padding:5px 0;font-size:14.5px;border:none}
.tl li:before{content:"";position:absolute;left:-18px;top:11px;width:10px;height:10px;border-radius:50%;background:var(--panel);border:2px solid var(--muted)}
.tl li.add:before{border-color:var(--info);background:var(--info)}
.tl li.adj:before{border-color:var(--accent);background:var(--accent)}
.okbanner{display:flex;gap:12px;align-items:center;background:var(--ok-bg);color:var(--ok);border-radius:12px;padding:12px 14px;margin-bottom:12px;font-weight:600}
.okbanner i{font-style:normal;width:30px;height:30px;border-radius:50%;background:var(--ok);color:#fff;display:grid;place-items:center;font-size:16px;flex:none}
.okbanner small{display:block;font-weight:500;opacity:.85}
.sig-wrap{margin-top:12px}
.sig-wrap .lbl{display:block;margin-bottom:6px}
.btn.big.primary{box-shadow:0 2px 0 rgba(0,0,0,.12)}
.opacts{display:grid;gap:10px}
.empty .ico{width:54px;height:54px;border-radius:50%;background:var(--soft);display:grid;place-items:center;margin:0 auto 10px;font-size:24px}
/* subnavegación de inventario */
.subnav{display:flex;gap:4px;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:4px;margin:0 0 14px;width:fit-content;max-width:100%;overflow-x:auto}
.subnav button{border:none;background:none;padding:8px 16px;border-radius:9px;font:700 14.5px var(--font-head);color:var(--muted);cursor:pointer;white-space:nowrap}
.subnav button[aria-selected=true]{background:var(--panel);color:var(--ink);box-shadow:var(--shadow)}
.toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0 0 10px}
.toolbar .grow{flex:1;min-width:160px}
.toolbar h3{margin-right:auto}
tr.stale td{background:none}
tr.stale td:first-child{box-shadow:inset 3px 0 0 var(--accent)}
.lvl{display:flex;align-items:center;gap:8px;min-width:120px}
.lvl .prog{flex:1;height:6px}
.lvl.zero .prog i{background:var(--bad)}
.agotada{font:700 11.5px var(--font-body);color:var(--bad);background:var(--bad-bg);padding:1px 8px;border-radius:999px}
td input[type=number]{border-radius:8px;min-height:36px;font-family:var(--font-head);font-weight:700;font-size:17px}
tbody tr:hover td{background:var(--soft)}
/* paneles */
.kpi{position:relative;overflow:hidden}
.kpi:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--line)}
.kpi:first-child:before{background:var(--accent)}
.kpi b{font-size:32px;display:block;overflow-wrap:anywhere}
.kpi b.txt{font-size:22px;line-height:1.2;padding:5px 0}
.rank-row .pos{width:26px;height:26px;border-radius:50%;background:var(--soft);display:grid;place-items:center;font-size:13px}
.rank-row:nth-child(1) .pos{background:var(--accent);color:var(--accent-ink)}
.rank-row:nth-child(2) .pos,.rank-row:nth-child(3) .pos{background:var(--warn-bg);color:var(--warn)}
.rank-row:hover{background:var(--soft)}
.bar{height:8px}
.cal-cell{transition:background .12s}
.cal-cell.sel{background:var(--warn-bg)}
.sec-title h3:before{content:"";display:inline-block;width:4px;height:16px;border-radius:2px;background:var(--accent);margin-right:9px;vertical-align:-2px}
@media (max-width:560px){
  .top-in{flex-wrap:nowrap;gap:6px}.top-in .btn.sm{padding:5px 9px}.brand{min-width:0}.brand-txt{min-width:0;font-size:17px}
  .hero-sum{display:none}.tcard-top,.tcard-main{padding-left:14px;padding-right:14px}
  .tcard .nod{font-size:34px}
  .subnav{width:100%}.subnav button{flex:1;padding:8px 6px}
  .rank-row{grid-template-columns:26px 1fr auto;}.rank-row .bar{grid-column:2/-1;grid-row:2}
  .kpi b{font-size:27px}
}

.rank-row{width:100%;background:none;border:none;border-top:1px solid var(--line);text-align:left;cursor:pointer;font:inherit;color:inherit}
.rank-row:first-child{border-top:none}
.rank-row .go{color:var(--muted);font-size:20px;line-height:1}
.rank-row{grid-template-columns:28px minmax(120px,1.3fr) minmax(90px,2fr) auto 14px}
.thumb{display:block;width:150px;height:105px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:var(--soft)}
.thumbs{display:flex;gap:8px;flex-wrap:wrap}
.photo-card{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:14px}
.photo-card figure{margin:0;background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
.photo-card img{display:block;width:100%;height:190px;object-fit:cover;background:var(--soft)}
.photo-card figcaption{padding:9px 12px;font-size:13.5px;line-height:1.35}
.photo-card figcaption b{font-family:var(--font-head);font-size:16px}
.photo-card figcaption span{color:var(--muted);display:block}
@media (max-width:560px){.rank-row{grid-template-columns:26px 1fr auto 12px}.rank-row .bar{grid-column:2/-1;grid-row:2}}
</style>
</head>
<body>
<div id="app"><div class="loading">Cargando…</div></div>
<div id="signRoot"></div>
<input type="file" id="photoInp" accept="image/*" capture="environment" hidden>
<datalist id="nodList"></datalist>

<script>
(function(){
'use strict';

var $=function(s){return document.querySelector(s)};
var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
var pad=function(n){return String(n).padStart(2,'0')};
function ymd(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())}
function todayStr(){return ymd(new Date())}
function parseYmd(s){var p=s.split('-').map(Number);return new Date(p[0],p[1]-1,p[2])}
function shiftDate(s,n){var d=parseYmd(s);d.setDate(d.getDate()+n);return ymd(d)}
function fmtDate(s){return parseYmd(s).toLocaleDateString('es-MX',{weekday:'long',day:'numeric',month:'long'})}
function fmtTime(ts){return ts?new Date(ts).toLocaleString('es-MX',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}):''}
function ago(ts){
  if(!ts)return 'sin fecha';
  var ms=Date.now()-ts,d=Math.floor(ms/86400000);
  if(d<=0){var h=Math.floor(ms/3600000);return h<1?'hace minutos':'hace '+h+' h'}
  return 'hace '+d+(d===1?' día':' días');
}
var sumK=function(a,k){return (a||[]).reduce(function(s,x){return s+(Number(x[k])||0)},0)};
var STALE_DAYS=7;
function isStale(ts){return !ts||(Date.now()-ts)>STALE_DAYS*86400000}

var S={ready:false,isAdmin:false,isStaff:false,role:'',tractor:'',stats:null,cal:null,ops:[],dayNotes:[],date:todayStr(),trips:[],pending:[],cache:{},nods:{},stock:{almacen:null,oficina:null},stockTs:{almacen:null,oficina:null},
  cfg:{perUnit:4},opNum:null,opName:'',notifs:[],rep:null,nodHist:null};
var U={tab:'asig',viewAs:false,mustChange:false,imp:{rows:[],loading:false,err:''},calMes:'',calSel:'',statsPreset:'8w',modal:null,f:{},verify:null,reqOpen:null,photo:null,toast:'',filter:'all',importMsg:null,invMsg:null,loginErr:'',invSort:'cod',busy:false};

/* ---------- estados ---------- */
var STATUS={asignado:['Sin aceptar','neutral'],aceptado:['Contando bandas','info'],esperando:['Esperando bandas','warn'],listo:['Listo para firmar','info'],confirmado:['Confirmado','ok']};
function norm(t){t.hasPending=(t.requests||[]).some(function(r){return r.status==='pendiente'});return t}
function tag(s){var x=STATUS[s]||STATUS.asignado;return '<span class="tag '+x[1]+'">'+x[0]+'</span>'}
function chips(t){return (t.req||[]).map(function(r){return '<span class="chip '+String(r.tipo).toLowerCase()+'">'+r.n+' '+esc(r.tipo)+(r.n===1?'':'S')+'</span>'}).join(' ')}
function difOf(t){return t.total==null?null:t.total-t.required}
function difTxt(d){return d==null?'—':(d>0?'+'+d:d<0?'−'+Math.abs(d):'0')}
function difCls(d){return d==null?'':d<0?'neg':d>0?'pos':'zero'}
function findTrip(id){
  return S.trips.filter(function(t){return t.id===id})[0]||S.pending.filter(function(t){return t.id===id})[0]||S.cache[id];
}
/* Busca el viaje que contiene la solicitud en TODAS las listas (la de pendientes puede estar más fresca que la del día) */
function findTripWithReq(id,rid){
  var lists=[S.pending,S.trips,[S.cache[id]]];
  for(var i=0;i<lists.length;i++){for(var j=0;j<lists[i].length;j++){var t=lists[i][j];
    if(t&&t.id===id&&(t.requests||[]).some(function(x){return String(x.id)===String(rid)}))return t}}
  return null;
}
function pendingReqs(t){return (t.requests||[]).filter(function(r){return r.status==='pendiente'})}
function plural(n){return n+' '+(n===1?'banda':'bandas')}
function photoUrl(t,kind){return '/foto.php?id='+encodeURIComponent(t.id)+'&t='+kind+'&v='+(kind==='firma'?(t.firmaTs||0):(t.photoTs||0))}

/* ---------- red ---------- */
function sessionLost(){
  clearSession();U.loginErr='Tu sesión expiró. Entra de nuevo.';render();
}
async function post(url,body){
  var r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(body)});
  var d;try{d=await r.json()}catch(e){throw new Error('El servidor respondió algo inesperado')}
  if(r.status===401){sessionLost();throw new Error(d.message||'Sesión expirada')}
  if(!d.success){var er=new Error(d.message||d.error||'Ocurrió un error');er.data=d;throw er}
  return d;
}
async function get(url){
  var r=await fetch(url,{credentials:'same-origin'});
  var d;try{d=await r.json()}catch(e){throw new Error('El servidor respondió algo inesperado')}
  if(r.status===401){sessionLost();throw new Error('Sesión expirada')}
  return d;
}
function fail(e){toast('Error: '+((e&&e.message)||'sin conexión'))}

async function cargarViajes(silent){
  try{var d=await get('/api_viajes.php?fecha='+encodeURIComponent(S.date));
    if(d.success){S.trips=(d.data||[]).map(norm);requestRender()}else if(!silent)fail({message:d.error||d.message})}
  catch(e){if(!silent)fail(e)}
}
async function cargarPendientes(silent){
  try{var d=await get('/api_viajes.php?pendientes=1');
    if(d.success){S.pending=(d.data||[]).map(norm);requestRender()}else if(!silent)fail({message:d.error||d.message})}
  catch(e){if(!silent)fail(e)}
}
async function cargarInventario(silent){
  try{var d=await get('/api_inventario.php');
    if(d.success){S.nods=d.nodrizas||{};S.stock=d.stock||{almacen:0,oficina:0};S.stockTs=d.stockTs||{};S.cfg.perUnit=d.perUnit||4;requestRender()}
    else if(!silent)fail({message:d.error||d.message})}
  catch(e){if(!silent)fail(e)}
}
function statsRange(){
  var t=todayStr(),p=U.statsPreset,d0,d1=t;
  if(p==='custom'&&U.f.stDesde&&U.f.stHasta){d0=U.f.stDesde;d1=U.f.stHasta}
  else if(p==='mes'){d0=t.slice(0,8)+'01'}
  else if(p==='4w')d0=shiftDate(t,-27);
  else if(p==='12w')d0=shiftDate(t,-83);
  else if(p==='anio')d0=t.slice(0,4)+'-01-01';
  else d0=shiftDate(t,-55);
  return {desde:d0,hasta:d1};
}
async function cargarStats(silent){
  var r=statsRange();
  try{var d=await get('/api_estadisticas.php?tipo=panel&desde='+r.desde+'&hasta='+r.hasta);
    if(d.success){S.stats=d;requestRender()}else if(!silent)fail({message:d.error||d.message})}
  catch(e){if(!silent)fail(e)}
}
function calMes(){return U.calMes||(U.calMes=todayStr().slice(0,7))}
async function cargarCal(silent){
  try{var d=await get('/api_estadisticas.php?tipo=calendario&mes='+calMes());
    if(d.success&&d.mes===calMes()){S.cal=d;requestRender()}else if(!d.success&&!silent)fail({message:d.error||d.message})}
  catch(e){if(!silent)fail(e)}
}
async function cargarNotasDia(silent){
  var f=S.date;
  try{var d=await get('/api_estadisticas.php?tipo=comentarios&fecha='+f);
    if(d.success&&f===S.date){S.dayNotes=d.comentarios||[];requestRender()}}
  catch(e){if(!silent)fail(e)}
}
async function cargarUsuarios(silent){
  if(!S.isAdmin)return;
  try{var d=await post('/api_usuarios.php',{accion:'listar'});
    S.users=d.usuarios||[];S.ops=S.users.filter(function(u){return u.rol==='operador'&&u.estado==='activo'}).map(function(u){return {num:u.num,nombre:u.nombre,tractor:u.tractor||''}});requestRender()}
  catch(e){if(!silent)fail(e)}
}
async function cargarNotifs(silent){
  try{var d=await get('/api_notificaciones.php');if(d.success){S.notifs=d.data||[];requestRender()}}
  catch(e){if(!silent)fail(e)}
}
async function refreshAfter(d){
  if(d&&d.trip)S.cache[d.trip.id]=norm(d.trip);
  var jobs=[cargarViajes(true)];
  if(S.isAdmin)jobs.push(cargarPendientes(true));
  if(S.isStaff)jobs.push(cargarInventario(true));
  await Promise.all(jobs);
}
async function act(accion,id,extra){
  var d=await post('/api_viajes.php',Object.assign({accion:accion,id:id},extra||{}));
  await refreshAfter(d);return d;
}
function setDate(d){S.date=d;S.trips=[];cargarViajes();render()}

/* ---------- sesión ---------- */
function applySession(data){
  S.opNum=data.num_empleado;S.opName=data.nombre||'';S.role=data.rol||'operador';S.isAdmin=(S.role==='admin');S.isStaff=(S.role==='admin'||S.role==='administrativo');
  S.tractor=data.tractor||'';U.viewAs=false;U.mustChange=!!data.debe_cambiar;U.tab=S.isAdmin?'asig':'panel';
  if(U.mustChange)return;
  afterAuthLoad();
}
function afterAuthLoad(){
  if(S.isAdmin){cargarPendientes();cargarUsuarios(true)}
  if(S.isStaff){cargarInventario();if(U.tab==='panel')cargarStats(true)}else cargarNotifs();
  cargarViajes();
}
function clearSession(){
  S.opNum=null;S.opName='';S.isAdmin=false;S.isStaff=false;S.role='';S.stats=null;S.cal=null;S.ops=[];S.dayNotes=[];U.mustChange=false;U.imp={rows:[],loading:false,err:''};S.notifs=[];S.trips=[];S.pending=[];S.cache={};S.nods={};S.rep=null;
  U.viewAs=false;U.modal=null;U.tab='asig';U.f={};U.verify=null;U.reqOpen=null;U.photo=null;closeSign(true);
}

/* ---------- polling: la pantalla se actualiza sola ---------- */
function pollOK(){
  if(document.hidden||!S.ready||!S.opNum||U.mustChange)return false;
  if(SIG.open||U.verify||U.reqOpen||U.busy)return false;
  var a=document.activeElement;
  if(a&&/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)&&a.closest&&a.closest('#app'))return false;
  return true;
}
function refreshAll(){
  if(S.isStaff&&!U.viewAs){
    if(S.isAdmin)cargarPendientes(true);
    cargarViajes(true);
    if(U.tab==='inv'||U.tab==='rep')cargarInventario(true);
    if(U.tab==='panel')cargarStats(true);
    if(U.tab==='cal'){cargarCal(true);cargarNotasDia(true)}
  }else{cargarViajes(true);cargarNotifs(true)}
}
setInterval(function(){if(pollOK())refreshAll()},15000);
document.addEventListener('visibilitychange',function(){if(!document.hidden&&pollOK())refreshAll()});

/* ---------- acciones del operador ---------- */
async function doAccept(id){await act('aceptar',id);toast('Asignación aceptada')}
function startVerify(id){
  var t=findTrip(id);var sys=(t&&typeof t.nodNow==='number')?t.nodNow:0;
  U.verify={tripId:id,sys:sys,mode:sys>0?'ask':'count'};U.photo=null;U.f.vN='';render();
}
async function sendCount(count){
  var v=U.verify;if(!v)return;
  var body={conteo:count};
  if(U.photo&&(v.sys>0&&count<v.sys))body.foto=U.photo;
  else if(U.photo&&count!==v.sys)body.foto=U.photo;
  try{await act('contar',v.tripId,body)}
  catch(e){
    if(e.data&&e.data.needPhoto){v.sys=e.data.sys;v.mode='count';U.f.vN=String(count);render()}
    throw e;
  }
  U.verify=null;U.photo=null;toast('Conteo guardado');
}
async function sendRequest(id,n,note){await act('solicitar',id,{n:n,nota:note||''})}

/* ---------- foto de la nodriza ---------- */
function compressImage(file,maxSide,q){
  return new Promise(function(res,rej){
    var url=URL.createObjectURL(file),im=new Image();
    im.onload=function(){
      var w=im.naturalWidth,h=im.naturalHeight,k=Math.min(1,maxSide/Math.max(w,h));
      var c=document.createElement('canvas');c.width=Math.round(w*k);c.height=Math.round(h*k);
      c.getContext('2d').drawImage(im,0,0,c.width,c.height);URL.revokeObjectURL(url);
      res(c.toDataURL('image/jpeg',q));
    };
    im.onerror=function(){URL.revokeObjectURL(url);rej(new Error('No se pudo leer la foto'))};
    im.src=url;
  });
}

/* ---------- firma a pantalla completa ---------- */
var SIG={open:false,id:null,strokes:[],cur:null,c:null,w:0,h:0,dpr:1,busy:false};
function sigPoints(){return SIG.strokes.reduce(function(s,k){return s+k.length},0)}
function openSign(id){
  var t=findTrip(id);if(!t)return;
  SIG.open=true;SIG.id=id;SIG.strokes=[];SIG.cur=null;SIG.busy=false;
  var root=$('#signRoot');
  root.innerHTML='<div class="signfs" role="dialog" aria-modal="true" aria-label="Firma">'+
    '<div class="signfs-top"><div><div class="t1">'+esc(t.opName||('Operador '+t.opNum))+'</div><div class="t2">Nodriza '+esc(t.nodriza)+' · firma para aceptar el viaje</div></div>'+
    '<div><div class="big">'+t.total+'</div><div class="t2" style="text-align:right">bandas</div></div></div>'+
    '<div class="signfs-pad"><canvas id="sigc"></canvas><div class="signfs-line"></div><div class="signfs-hint" id="sigHint">Firma aquí con tu dedo</div></div>'+
    '<div id="sigErr"></div>'+
    '<div class="signfs-bar"><button class="btn" id="sigClear">Borrar</button><button class="btn" id="sigCancel">Volver</button>'+
    '<button class="btn ok grow" id="sigOk" disabled>Firmar y confirmar</button></div></div>';
  document.body.style.overflow='hidden';
  SIG.c=$('#sigc');sizeSig();bindSig();
  window.addEventListener('resize',sizeSig);
}
function closeSign(silent){
  if(!SIG.open&&silent)return;
  SIG.open=false;SIG.strokes=[];SIG.cur=null;
  var root=$('#signRoot');if(root)root.innerHTML='';
  document.body.style.overflow='';
  window.removeEventListener('resize',sizeSig);document.removeEventListener('keydown',sigEsc);
  try{if(screen.orientation&&screen.orientation.unlock)screen.orientation.unlock()}catch(e){}
  try{if(document.fullscreenElement&&document.exitFullscreen)document.exitFullscreen().catch(function(){})}catch(e){}
}
function sizeSig(){
  var c=SIG.c;if(!c)return;
  var r=c.getBoundingClientRect(),dpr=window.devicePixelRatio||1;
  SIG.w=r.width;SIG.h=r.height;SIG.dpr=dpr;
  c.width=Math.max(1,Math.round(r.width*dpr));c.height=Math.max(1,Math.round(r.height*dpr));
  drawSig();
}
function drawStrokes(g,W,H,lw){
  g.lineCap='round';g.lineJoin='round';g.strokeStyle='#111';g.fillStyle='#111';g.lineWidth=lw;
  SIG.strokes.forEach(function(k){
    if(!k.length)return;
    if(k.length===1){g.beginPath();g.arc(k[0][0]*W,k[0][1]*H,lw/2,0,Math.PI*2);g.fill();return}
    g.beginPath();g.moveTo(k[0][0]*W,k[0][1]*H);
    for(var i=1;i<k.length;i++)g.lineTo(k[i][0]*W,k[i][1]*H);
    g.stroke();
  });
}
function drawSig(){
  var c=SIG.c;if(!c)return;var g=c.getContext('2d');
  g.setTransform(SIG.dpr,0,0,SIG.dpr,0,0);g.clearRect(0,0,SIG.w,SIG.h);
  drawStrokes(g,SIG.w,SIG.h,Math.max(3.5,Math.min(SIG.w,SIG.h*2)/170));
}
function bindSig(){
  var c=SIG.c;
  function pt(e){var b=c.getBoundingClientRect();return [Math.min(1,Math.max(0,(e.clientX-b.left)/b.width)),Math.min(1,Math.max(0,(e.clientY-b.top)/b.height))]}
  function upd(){var b=$('#sigOk');if(b)b.disabled=SIG.busy||sigPoints()<12;var h=$('#sigHint');if(h)h.style.display=SIG.strokes.length?'none':'block'}
  c.addEventListener('pointerdown',function(e){
    e.preventDefault();try{c.setPointerCapture(e.pointerId)}catch(x){}
    SIG.cur=[pt(e)];SIG.strokes.push(SIG.cur);drawSig();upd();
  });
  c.addEventListener('pointermove',function(e){
    if(!SIG.cur)return;e.preventDefault();
    var evs=(e.getCoalescedEvents&&e.getCoalescedEvents())||[e];
    evs.forEach(function(x){SIG.cur.push(pt(x))});drawSig();
  });
  function end(){if(!SIG.cur)return;SIG.cur=null;upd()}
  c.addEventListener('pointerup',end);c.addEventListener('pointercancel',end);
  $('#sigClear').addEventListener('click',function(){SIG.strokes=[];SIG.cur=null;drawSig();upd();var er=$('#sigErr');if(er)er.innerHTML=''});
  $('#sigCancel').addEventListener('click',function(){closeSign()});
  $('#sigOk').addEventListener('click',submitSign);
  document.addEventListener('keydown',sigEsc);
}
function sigEsc(e){if(e.key==='Escape'&&SIG.open)closeSign()}
function exportSig(){
  var W=720,ratio=Math.min(0.7,Math.max(0.3,SIG.h/SIG.w)),H=Math.round(W*ratio);
  var o=document.createElement('canvas');o.width=W;o.height=H;var g=o.getContext('2d');
  g.fillStyle='#fff';g.fillRect(0,0,W,H);drawStrokes(g,W,H,4.5);
  return o.toDataURL('image/png');
}
async function submitSign(){
  if(SIG.busy||sigPoints()<12)return;
  SIG.busy=true;var b=$('#sigOk');if(b){b.disabled=true;b.textContent='Guardando…'}
  var er=$('#sigErr');if(er)er.innerHTML='';
  try{
    var img=exportSig();
    await act('firmar',SIG.id,{firma:img,comentario:(U.f['opCom_'+SIG.id]||'').trim()});
    closeSign();toast('Viaje firmado y confirmado');render();
  }catch(e){
    SIG.busy=false;
    var b2=$('#sigOk');if(b2){b2.disabled=false;b2.textContent='Firmar y confirmar'}
    var er2=$('#sigErr');if(er2)er2.innerHTML='<div class="signfs-err">'+esc(e.message||'No se pudo guardar la firma')+'</div>';
  }
}

/* ---------- acciones del admin ---------- */
function srcOpts(sel){
  var al=S.stock.almacen,of=S.stock.oficina;
  return [['almacen','Almacén ('+(al==null?'?':al)+')'],['oficina','Oficina ('+(of==null?'?':of)+')'],['nodriza','Otra nodriza'],['otro','Otro lugar']]
    .map(function(o){return '<option value="'+o[0]+'"'+(sel===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('');
}
function needCode(src,code){
  if(src==='nodriza'&&!/^[A-Za-z]{1,3}\d{3,5}$/.test((code||'').trim()))throw new Error('Escribe el código de la nodriza de donde salieron');
}
async function approveReq(id,rid){
  var t=findTripWithReq(id,rid);
  if(!t){await Promise.all([cargarViajes(true),cargarPendientes(true)]);t=findTripWithReq(id,rid)}
  var r=t&&(t.requests||[]).filter(function(x){return String(x.id)===String(rid)})[0];
  if(!r)throw new Error('La solicitud ya fue atendida o cancelada. Se actualizó la pantalla.');
  var n=parseInt(U.f['apN_'+rid]!=null?U.f['apN_'+rid]:r.n,10),src=U.f['apSrc_'+rid]||'almacen',code=(U.f['apCode_'+rid]||'').trim().toUpperCase(),note=(U.f['apNote_'+rid]||'').trim();
  if(!(n>0))throw new Error('Escribe cuántas bandas entregas');
  needCode(src,code);
  var d=await act('aprobar_solicitud',id,{solicitud_id:String(rid),cantidad:n,origen:src,codigo:code,nota:note});
  toast('Aprobada: '+plural(n)+' desde '+d.origen+'. Inventario actualizado.');
}
async function rejectReq(id,rid){
  var note=(U.f['apNote_'+rid]||'').trim();
  await act('rechazar_solicitud',id,{solicitud_id:String(rid),nota:note});toast('Solicitud rechazada');
}
async function doDeliver(id){
  var n=parseInt(U.f.dvN,10),src=U.f.dvSrc||'almacen',code=(U.f.dvCode||'').trim().toUpperCase(),note=(U.f.dvNote||'').trim();
  if(!(n>0))throw new Error('Escribe cuántas bandas entregas');needCode(src,code);
  await act('entregar',id,{n:n,origen:src,codigo:code,nota:note});
  U.f.dvN='';U.f.dvNote='';U.f.dvCode='';toast('Entrega registrada e inventario actualizado');
}
async function doAdjust(id){
  var n=parseInt(U.f.adN,10);if(!(n>=0))throw new Error('Escribe el total correcto');
  await act('ajustar_total',id,{total:n,nota:(U.f.adNote||'').trim()});U.f.adN='';U.f.adNote='';toast('Total actualizado');
}
async function delTrip(id){
  await post('/api_viajes.php',{accion:'eliminar_viaje',id:id});
  U.modal=null;delete S.cache[id];await refreshAfter();toast('Viaje eliminado');
}
async function delDay(){
  if(!confirm('¿Borrar las '+S.trips.length+' asignaciones del '+S.date+'? No se puede deshacer y NO revierte el inventario.'))return;
  await post('/api_viajes.php',{accion:'eliminar_dia',fecha:S.date});await refreshAfter();toast('Día borrado');
}

/* --- asignaciones: importar (la lectura la hace el servidor; aquí se revisa y corrige antes de cargar) --- */
var impTimer=null;
function schedulePreview(){
  clearTimeout(impTimer);
  var txt=(U.f.impText||'').trim();
  if(!txt){U.imp={rows:[],loading:false,err:''};render();return}
  U.imp.loading=true;
  impTimer=setTimeout(previewImport,450);
}
async function previewImport(){
  var txt=U.f.impText||'';
  try{
    var d=await post('/api_viajes.php',{accion:'previsualizar',texto:txt});
    if(txt!==(U.f.impText||''))return;
    U.imp={rows:(d.filas||[]).map(function(f){
      var cs=f.crearSugerido;return Object.assign({},f,{ed:{opNum:f.opNum||'',tractor:f.tractor||'',nodriza:f.nodriza||'',crear:!!cs,cNum:cs?cs.num:'',cName:cs?(cs.nombre||''):(f.opName||''),guardar:false,skip:false}});
    }),loading:false,err:''};
  }catch(e){U.imp={rows:[],loading:false,err:e.message||'No se pudo leer la lista'}}
  render();
}
function opByNum(n){return S.ops.filter(function(o){return o.num===n})[0]}
function rowInfo(r){
  var e=r.ed,miss=[];
  if(e.crear){if(!/^[A-Za-z0-9]{3,20}$/.test((e.cNum||'').trim()))miss.push('número del operador nuevo')}
  else if(!e.opNum)miss.push('operador');
  if(!/^[A-Za-z]{1,3}\d{3,5}$/.test((e.nodriza||'').trim()))miss.push('nodriza');
  if(!(r.req||[]).length)miss.push('cantidad');
  if((e.tractor||'')!==''&&!/^[A-Za-z0-9\-]{1,20}$/.test(e.tractor))miss.push('tractor válido');
  var op=e.crear?null:opByNum(e.opNum),hab=op?op.tractor:'';
  return {miss:miss,ready:!miss.length,nodNueva:!miss.length&&!S.nods[(e.nodriza||'').trim().toUpperCase()],op:op,hab:hab,cambio:!!(op&&hab&&e.tractor&&e.tractor!==hab)};
}
function impSummary(){
  var rows=U.imp.rows,n=0,skip=0,pend=0;
  rows.forEach(function(r){if(r.ed.skip){skip++;return}if(rowInfo(r).ready)n++;else pend++});
  return {n:n,skip:skip,pend:pend,total:rows.length};
}
async function doImport(){
  var date=U.f.impDate||S.date,sm=impSummary();
  if(!sm.total)throw new Error('Pega primero la lista de asignaciones');
  if(sm.pend)throw new Error('Faltan datos en '+sm.pend+' renglón(es). Complétalos o márcalos como "No cargar".');
  if(!sm.n)throw new Error('No hay renglones para cargar');
  var viajes=[];
  U.imp.rows.forEach(function(r){
    if(r.ed.skip)return;var e=r.ed,inf=rowInfo(r);
    var v={opNum:e.crear?'':e.opNum,opName:r.opName||'',tractor:(e.tractor||'').trim(),nodriza:(e.nodriza||'').trim().toUpperCase(),reqText:r.reqText,req:r.req,guardarTractor:!!(e.guardar&&inf.cambio)};
    if(e.crear)v.crear={num:(e.cNum||'').trim(),nombre:(e.cName||'').trim()};
    viajes.push(v);
  });
  var sr=await post('/api_viajes.php',{accion:'importar_viajes',fecha:date,viajes:viajes});
  U.importMsg={made:sr.made,skipped:sr.skipped,creados:sr.creados||0,nods:sr.nodrizasCreadas||[],errores:sr.errores||[]};
  if(!(sr.errores||[]).length){U.f.impText='';U.imp={rows:[],loading:false,err:''}}
  else schedulePreview();
  await cargarUsuarios(true);cargarInventario(true);
  if(date!==S.date)setDate(date);else cargarViajes();
}

/* --- inventario --- */
async function invPost(body){return post('/api_inventario.php',body)}
async function setNodriza(code,n,motivo){await invPost({accion:'actualizar_nodriza',codigo:code,bandas:n,motivo:motivo||''});await cargarInventario(true)}
async function doInvImport(){
  var lines=(U.f.invText||'').split(/\r?\n/),ok=[],bad=[],stock={almacen:null,oficina:null},touch=false;
  lines.forEach(function(line){
    var l=line.trim();if(!l)return;var p=l.split(/[\t,;]+|\s+/).filter(Boolean);
    var code=(p[0]||'').toUpperCase().replace('Á','A'),n=parseInt(p[p.length-1],10);
    if(p.length<2||isNaN(n)||n<0){bad.push(l);return}
    if(code==='ALMACEN'){stock.almacen=n;touch=true}
    else if(code==='OFICINA'){stock.oficina=n;touch=true}
    else if(/^[A-Z]{1,3}\d{3,5}$/.test(code))ok.push([code,n]);
    else bad.push(l);
  });
  if(!ok.length&&!touch)throw new Error('No se reconoció ninguna línea');
  var ir=await invPost({accion:'importar',nodrizas:ok,stock:touch?stock:null});
  U.invMsg={n:ok.length,stock:touch,bad:bad,r:ir.resumen||null};U.f.invText='';await cargarInventario(true);
}
async function clearReadNotifs(){var d=await post('/api_notificaciones.php',{accion:'borrar_leidas'});toast('Avisos leídos borrados ('+d.borradas+')')}

/* --- reportes --- */
function repDefaults(){
  if(!U.f.repHasta)U.f.repHasta=todayStr();
  if(!U.f.repDesde)U.f.repDesde=shiftDate(todayStr(),-6);
  if(!U.f.repTipo)U.f.repTipo='todos';
}
async function loadReport(){
  repDefaults();
  var d0=parseYmd(U.f.repDesde).getTime(),d1=parseYmd(U.f.repHasta).getTime()+86400000;
  if(d1<=d0)throw new Error('La fecha final debe ser igual o posterior a la inicial');
  var q='/api_reportes.php?desde='+d0+'&hasta='+d1+'&tipo='+encodeURIComponent(U.f.repTipo)+'&codigo='+encodeURIComponent((U.f.repCod||'').trim());
  var d=await get(q);if(!d.success)throw new Error(d.error||d.message||'No se pudo generar el reporte');
  S.rep={mov:d.movimientos||[],disc:d.discrepancias||[]};
}
var MOVTIPO={entrega:'Entrega a operador',conteo_operador:'Conteo del operador',firma:'Firma',ajuste_manual:'Ajuste manual',ajuste_viaje:'Ajuste de total',transferencia:'Transferencia',
  stock_entrada:'Entrada',stock_salida:'Salida',stock_ajuste:'Conteo/ajuste de stock',importacion:'Carga masiva',alta:'Alta de nodriza',baja:'Baja de nodriza'};
function movWhere(m){return m.codigo?'Nodriza '+esc(m.codigo):(m.ubicacion==='ALMACEN'?'Almacén':m.ubicacion==='OFICINA'?'Oficina':esc(m.ubicacion))}
function csvDownload(filename,rows){
  var csv='\ufeff'+rows.map(function(r){return r.map(function(c){return '"'+String(c==null?'':c).replace(/"/g,'""')+'"'}).join(',')}).join('\r\n');
  var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'}),a=document.createElement('a');
  a.href=URL.createObjectURL(blob);a.download=filename;a.style.display='none';document.body.appendChild(a);a.click();document.body.removeChild(a);
  setTimeout(function(){URL.revokeObjectURL(a.href)},2000);
}
function exportCsv(){
  var rows=[['Fecha','Operador','Nombre','Tractor','Nodriza','Asignación','Requerido','Sistema decía','Conteo operador','Entregadas','Ajustes','Total','Diferencia','Estado','Firmó','Con foto','Comentario']];
  S.trips.forEach(function(t){rows.push([t.fecha,t.opNum,t.opName,t.tractor||'',t.nodriza,t.reqText,t.required,t.sysInv==null?'':t.sysInv,t.base==null?'':t.base,sumK(t.entregas,'n'),sumK(t.ajustes,'delta'),t.total==null?'':t.total,t.total==null?'':t.total-t.required,STATUS[t.status||'asignado'][0],t.firmaTs?fmtTime(t.firmaTs):'',t.hasPhoto?'Sí':'',t.comentario||''])});
  csvDownload('viajes_'+S.date+'.csv',rows);
}
function exportInvCsv(){
  var rows=[['Nodriza','Bandas','Última actualización','Actualizado por']];
  Object.keys(S.nods).sort().forEach(function(c){var x=S.nods[c];rows.push([c,x.bandas==null?'':x.bandas,x.updated?new Date(x.updated).toLocaleString('es-MX'):'',x.by||''])});
  rows.push(['ALMACEN',S.stock.almacen,S.stockTs.almacen?new Date(S.stockTs.almacen).toLocaleString('es-MX'):'','']);
  rows.push(['OFICINA',S.stock.oficina,S.stockTs.oficina?new Date(S.stockTs.oficina).toLocaleString('es-MX'):'','']);
  csvDownload('inventario_'+todayStr()+'.csv',rows);
}
function exportMovCsv(){
  if(!S.rep)return;
  var rows=[['Fecha y hora','Usuario','Tipo','Dónde','Antes','Después','Diferencia','Motivo']];
  S.rep.mov.forEach(function(m){rows.push([new Date(m.ts).toLocaleString('es-MX'),m.usuario,MOVTIPO[m.tipo]||m.tipo,m.codigo?'Nodriza '+m.codigo:m.ubicacion,m.antes==null?'':m.antes,m.despues,m.diferencia,m.motivo])});
  csvDownload('movimientos_'+U.f.repDesde+'_a_'+U.f.repHasta+'.csv',rows);
}

function exportStatsCsv(){
  var st=S.stats;if(!st)return;
  var rows=[['Periodo',st.desde+' a '+st.hasta],[],['POR OPERADOR'],['No. empleado','Operador','Tractor','Viajes','Solicitudes','Bandas que pidió','Bandas que se le pusieron','Viajes con solicitud','Conteos distintos al sistema','Última solicitud']];
  st.operadores.forEach(function(o){rows.push([o.opNum,o.opName,o.tractor,o.viajes,o.solicitudes,o.bandasPedidas,o.bandasPuestas,o.viajesConSolicitud,o.difs,o.ultimaSolicitud||''])});
  rows.push([],['POR NODRIZA'],['Nodriza','Viajes','Solicitudes','Bandas pedidas','Bandas que se le pusieron','Bandas ahora']);
  st.nodrizas.forEach(function(o){rows.push([o.codigo,o.viajes,o.solicitudes,o.bandasPedidas,o.bandasPuestas,o.bandasAhora==null?'':o.bandasAhora])});
  rows.push([],['POR SEMANA'],['Semana del','Al','Viajes','Solicitudes','Bandas pedidas','Bandas puestas']);
  st.semanas.forEach(function(o){rows.push([o.inicio,o.fin,o.viajes,o.solicitudes,o.bandasPedidas,o.bandasPuestas])});
  csvDownload('estadisticas_'+st.desde+'_a_'+st.hasta+'.csv',rows);
}
async function savePassword(forced){
  var a=U.f.pwA||'',n=U.f.pwN||'',n2=U.f.pwN2||'';
  if(!a||!n)throw new Error('Llena los campos');
  if(n!==n2)throw new Error('La confirmación no coincide');
  if(n.length<8)throw new Error('Usa al menos 8 caracteres');
  await post('/api_cuenta.php',{accion:'cambiar_password',actual:a,nueva:n});
  U.f.pwA=U.f.pwN=U.f.pwN2='';U.modal=null;
  if(forced){U.mustChange=false;toast('Contraseña actualizada');afterAuthLoad()}else toast('Contraseña actualizada');
}

/* ---------- vistas generales ---------- */
function header(adminMode){
  var right='';
  if(S.isAdmin)right+='<button class="btn sm" data-act="viewAs">'+(adminMode?'Ver como operador':'Administración')+'</button>';
  if(S.opNum&&!U.mustChange)right+='<button class="btn sm" data-act="myPwd">Contraseña</button>';
  if(S.opNum)right+='<button class="btn sm" data-act="logout">Salir</button>';
  var roleTxt={admin:'Administrador',administrativo:'Administrativo',operador:'Operador'}[S.role]||'';
  var who=!S.opNum?'Acceso':(S.isAdmin&&U.viewAs?'Vista de operador':(S.isStaff?esc(roleTxt)+(S.opName?' · '+esc(S.opName):''):'Operador'));
  return '<header class="top"><div class="top-in"><div class="brand"><div class="brand-mark" aria-hidden="true">B</div><div class="brand-txt">Control de bandas<small>'+who+'</small></div></div>'+right+'</div><div class="strap"></div></header>';
}
function dateBar(){
  return '<div class="datebar"><button class="btn icon" data-act="prevDay" aria-label="Día anterior">‹</button>'+
   '<input type="date" id="dateInp" value="'+S.date+'" aria-label="Fecha"><button class="btn icon" data-act="nextDay" aria-label="Día siguiente">›</button>'+
   (S.date!==todayStr()?'<button class="btn" data-act="today">Hoy</button>':'')+'<span class="sub">'+esc(fmtDate(S.date))+'</span></div>';
}
function loginView(){
  return '<div class="wrap narrow"><div class="card stack"><h2>Entra con tus credenciales</h2>'+
   '<p class="sub" style="margin:0">Ingresa tu número de empleado y contraseña.</p>'+
   '<label class="f"><span>Número de empleado</span><input type="text" id="lgNum" value="'+esc(U.f.lgNum||'')+'" placeholder="Ej. 134432 o ADMIN1" autocomplete="username" autocapitalize="characters" inputmode="text"></label>'+
   '<label class="f"><span>Contraseña</span><input type="password" id="lgPwd" value="'+esc(U.f.lgPwd||'')+'" placeholder="Tu contraseña" autocomplete="current-password"></label>'+
   (U.loginErr?'<div class="note bad" role="alert">'+esc(U.loginErr)+'</div>':'')+
   '<button class="btn primary big" data-act="login">Entrar</button></div></div>';
}
function pwdForm(forced){
  return '<div class="stack">'+
   (forced?'<div class="note warn">Por seguridad debes crear una contraseña nueva antes de continuar. No puede ser igual a tu número de empleado.</div>':'')+
   '<label class="f"><span>Contraseña actual</span><input type="password" id="pwA" value="'+esc(U.f.pwA||'')+'" autocomplete="current-password"></label>'+
   '<label class="f"><span>Contraseña nueva (mínimo 8, con letras y números)</span><input type="password" id="pwN" value="'+esc(U.f.pwN||'')+'" autocomplete="new-password"></label>'+
   '<label class="f"><span>Repite la contraseña nueva</span><input type="password" id="pwN2" value="'+esc(U.f.pwN2||'')+'" autocomplete="new-password"></label>'+
   '<button class="btn primary big" data-act="'+(forced?'savePwdForced':'savePwd')+'">Guardar contraseña</button></div>';
}
function pwdForcedView(){return '<div class="wrap narrow"><div class="card"><h2 style="margin-bottom:12px">Crea tu contraseña</h2>'+pwdForm(true)+'</div></div>'}

/* ---------- OPERADOR ---------- */
function initials(n){var p=String(n||'').trim().split(/\s+/).filter(Boolean);return ((p[0]||'?')[0]+(p.length>1?p[p.length>2?p.length-2:1][0]:'')).toUpperCase()}
function operatorView(){
  if(!S.opNum)return loginView();
  var mine=S.trips.filter(function(t){return t.opNum===S.opNum});
  var name=(mine[0]&&mine[0].opName)||S.opName||'';
  var sig=mine.filter(function(t){return t.firmado||t.status==='confirmado'}).length;
  var h='<div class="wrap narrow"><div class="hero"><div class="avatar" aria-hidden="true">'+esc(initials(name||S.opNum))+'</div><div><h2>'+esc(name||'Operador '+S.opNum)+'</h2><div class="sub">No. '+esc(S.opNum)+'</div></div>'+
    (mine.length?'<div class="hero-sum"><b>'+sig+'/'+mine.length+'</b><span>FIRMADOS</span></div>':'')+'</div>';
  if(S.notifs.length)h+='<div class="notifs">'+S.notifs.map(function(n){return '<div class="note info"><span>'+esc(n.text)+'<br><small>'+fmtTime(n.ts)+'</small></span><button class="btn sm" data-act="readNotif" data-id="'+esc(n.id)+'">Entendido</button></div>'}).join('')+'</div>';
  h+=dateBar();
  if(!mine.length)h+='<div class="card empty" style="margin-top:12px"><h3>No tienes viajes el '+esc(fmtDate(S.date))+'</h3><p style="margin:0">Cuando el administrador cargue la asignación, aparecerá aquí.</p></div>';
  else h+='<div style="margin-top:12px">'+mine.map(tripCard).join('')+'</div>';
  return h+'</div>';
}
function stepper(t){
  var done=[t.accepted||t.verified||t.firmado,t.verified||t.firmado,t.firmado],cur=done.indexOf(false);
  return '<div class="steps">'+['Aceptar','Contar','Firmar'].map(function(l,i){
    return '<div class="step'+(done[i]?' done':(i===cur?' now':''))+'" data-n="'+(i+1)+'">'+l+'</div>'}).join('')+'</div>';
}
function tripCard(t){
  var s=t.status||'asignado',body;
  if(s==='confirmado')body=confirmedBody(t);
  else if(!t.accepted)body=acceptBody(t);
  else if(!t.verified)body=(U.verify&&U.verify.tripId===t.id)?verifyPanel(t):'<button class="btn primary big" data-act="startVerify" data-id="'+esc(t.id)+'">Contar las bandas de la nodriza</button>';
  else body=readyBody(t,s);
  return '<section class="card tcard"><div class="tcard-top"><div class="trip-head"><div><div class="nod-lbl">Nodriza</div><div class="nod">'+esc(t.nodriza)+'</div></div>'+tag(s)+'</div>'+
    '<div class="need"><span class="lbl">Debes llevar</span>'+chips(t)+'</div>'+
    '<div class="need-meta"><span class="meta">Total <b>'+t.required+'</b> bandas</span>'+(t.tractor?'<span class="meta">Tractor <b>'+esc(t.tractor)+'</b></span>':'')+'</div></div>'+
    '<div class="tcard-main">'+stepper(t)+body+'</div></section>';
}
function acceptBody(t){
  return '<div class="stack"><div class="note info">Revisa lo que te asignaron y acéptalo para continuar. Después te pediremos contar las bandas de la nodriza.</div>'+
    '<button class="btn primary big" data-act="accept" data-id="'+esc(t.id)+'">Aceptar asignación</button></div>';
}
function verifyPanel(t){
  var v=U.verify;
  if(v.mode==='ask'){
    return '<div class="stack"><div class="note info">Según el sistema, la nodriza <b>'+esc(t.nodriza)+'</b> trae <b>'+v.sys+' bandas</b>. ¿Es correcto?</div>'+
     '<button class="btn primary big" data-act="verifyYes">Sí, son '+v.sys+'</button>'+
     '<button class="btn big" data-act="verifyNo">No, son otras</button>'+
     '<button class="btn" data-act="verifyCancel">Cancelar</button></div>';
  }
  return '<div class="stack"><div class="note '+(v.sys>0?'warn':'info')+'">'+(v.sys>0?'Cuenta las bandas de la nodriza y escribe el total real. El sistema decía <b>'+v.sys+'</b>.':'No hay un total registrado para esta nodriza. ¿Cuántas bandas trae?')+'</div>'+
    '<input type="number" inputmode="numeric" min="0" id="vN" class="num" value="'+esc(U.f.vN||'')+'" placeholder="0">'+
    '<div id="vDyn" class="stack">'+verifyDyn()+'</div></div>';
}
function verifyDyn(){
  var v=U.verify,n=parseInt(U.f.vN,10),valid=!isNaN(n)&&n>=0,less=valid&&v.sys>0&&n<v.sys,h='';
  if(less){
    h+='<div class="note warn">Son <b>menos</b> de las '+v.sys+' que indica el sistema. Para continuar, toma una foto de la nodriza.</div>';
    if(U.photo)h+='<img class="photo" alt="Foto de la nodriza" src="'+U.photo+'">';
    h+='<button class="btn big" data-act="pickPhoto">'+(U.photo?'Volver a tomar la foto':'Tomar foto de la nodriza')+'</button>';
  }else if(U.photo){
    h+='<img class="photo" alt="Foto de la nodriza" src="'+U.photo+'"><button class="btn" data-act="pickPhoto">Cambiar foto</button>';
  }
  var dis=!valid||(less&&!U.photo);
  h+='<button class="btn primary big" data-act="verifySave"'+(dis?' disabled':'')+'>Guardar conteo</button><button class="btn" data-act="verifyCancel">Cancelar</button>';
  return h;
}
function totalsBlock(t){
  var d=difOf(t);
  var pc=t.required>0?Math.max(0,Math.min(100,Math.round(t.total/t.required*100))):100;
  return '<div class="totals"><div><div class="lbl">Total de bandas</div><div class="big-n">'+t.total+'</div></div>'+
   '<div><div class="lbl" style="text-align:right">Diferencia</div><div class="dif '+difCls(d)+'">'+difTxt(d)+'</div></div>'+
   '<div class="prog'+(pc<100?' low':'')+'" style="grid-column:1/-1;margin-top:8px" role="img" aria-label="'+pc+'% de lo asignado"><i style="width:'+pc+'%"></i></div>'+
   '<div class="prog-l"><span>Llevas '+t.total+' de '+t.required+'</span><span>'+pc+'%</span></div></div>';
}
function historyList(t){
  var items=[];
  items.push('<li class="base">Conteo inicial: <b>'+t.base+'</b>'+(t.baseSource==='sistema'?' (según sistema)':' (reportado por ti)')+'</li>');
  (t.entregas||[]).forEach(function(e){items.push('<li class="add">+'+e.n+' desde '+esc(e.src)+' <span class="sub">'+fmtTime(e.ts)+'</span></li>')});
  (t.ajustes||[]).forEach(function(a){items.push('<li class="adj">Ajuste del administrador: total '+a.to+(a.note?' — '+esc(a.note):'')+' <span class="sub">'+fmtTime(a.ts)+'</span></li>')});
  return '<ul class="tl">'+items.join('')+'</ul>';
}
function readyBody(t,s){
  var h=totalsBlock(t)+historyList(t),d=difOf(t),pend=pendingReqs(t);
  if(s==='esperando'){
    var np=sumK(pend,'n');
    return h+'<div class="note warn" style="margin-bottom:10px">Pediste '+plural(np)+'. Espera a que el administrador lo apruebe; aquí te avisaremos.</div>'+
       '<button class="btn" data-act="cancelReq" data-id="'+esc(t.id)+'">Cancelar solicitud</button>';
  }
  if(d<0)h+='<div class="note warn" style="margin-bottom:10px">Te faltan '+plural(-d)+' para lo asignado.</div>';
  if(d>0)h+='<div class="note info" style="margin-bottom:10px">Traes '+d+' de más que lo asignado.</div>';
  if(U.reqOpen===t.id){
    return h+'<div class="stack"><label class="f"><span>¿Cuántas bandas necesitas?</span><input type="number" inputmode="numeric" min="1" id="rqN" class="num" value="'+esc(U.f.rqN||'')+'"></label>'+
      '<label class="f"><span>Nota para el administrador (opcional)</span><input type="text" id="rqNote" value="'+esc(U.f.rqNote||'')+'" placeholder="Ej. se rompieron 2"></label>'+
      '<button class="btn primary big" data-act="sendReq" data-id="'+esc(t.id)+'">Enviar solicitud</button><button class="btn" data-act="cancelReqForm">Cancelar</button></div>';
  }
  var short=d<0;
  h+='<label class="f" style="margin-bottom:12px"><span>Comentario para el administrador (opcional)</span><input type="text" maxlength="500" id="opCom_'+esc(t.id)+'" value="'+esc(U.f['opCom_'+t.id]||'')+'" placeholder="Ej. la banda 3 se ve gastada"></label>';
  return h+'<div class="stack">'+
    (short?'<button class="btn primary big" data-act="openReq" data-id="'+esc(t.id)+'">Pedir bandas (faltan '+(-d)+')</button><button class="btn big" data-act="startSign" data-id="'+esc(t.id)+'">Firmar con estas '+t.total+'</button>'
          :'<button class="btn primary big" data-act="startSign" data-id="'+esc(t.id)+'">Firmar y terminar</button><button class="btn big" data-act="openReq" data-id="'+esc(t.id)+'">Pedir bandas</button>')+'</div>';
}
function confirmedBody(t){
  return '<div class="okbanner"><i aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i><span>Viaje firmado<small>'+fmtTime(t.firmaTs)+'</small></span></div>'+totalsBlock(t)+historyList(t)+(t.comentario?'<div class="comment"><small>Tu comentario</small>'+esc(t.comentario)+'</div>':'')+'<div class="sig-wrap"><span class="lbl">Tu firma</span>'+sigHtml(t)+'</div>';
}
function sigHtml(t){
  if(!t.hasSig)return '<div class="sigimg ph">Sin firma guardada</div>';
  return '<img class="sigimg" alt="Firma del operador" src="'+photoUrl(t,'firma')+'">';
}
function photoHtml(t){
  if(!t.hasPhoto)return '';
  return '<a href="'+photoUrl(t,'foto')+'" target="_blank" rel="noopener"><img class="photo" alt="Foto de la nodriza" loading="lazy" src="'+photoUrl(t,'foto')+'"></a>';
}

/* ---------- ADMIN ---------- */
function adminView(){
  var tabs=S.isAdmin
    ?[['asig','Asignaciones'],['sol','Solicitudes'],['cal','Calendario'],['panel','Panel'],['inv','Inventario'],['rep','Reportes'],['usrs','Usuarios']]
    :[['panel','Panel'],['cal','Calendario'],['asig','Asignaciones'],['inv','Inventario'],['rep','Reportes']];
  var h='<div class="wrap"><div class="tabs" role="tablist">'+tabs.map(function(x){
    return '<button class="tab" role="tab" aria-selected="'+(U.tab===x[0])+'" data-act="tab" data-id="'+x[0]+'">'+x[1]+(x[0]==='sol'&&S.pending.length?'<span class="badge">'+S.pending.length+'</span>':'')+'</button>'}).join('')+'</div>';
  if(U.tab==='asig')h+=asigTab();else if(U.tab==='sol'&&S.isAdmin)h+=solTab();else if(U.tab==='inv')h+=invTab();else if(U.tab==='rep')h+=repTab();
  else if(U.tab==='panel')h+=panelTab();else if(U.tab==='cal')h+=calTab();else if(U.tab==='usrs'&&S.isAdmin)h+=usrsTab();else h+=panelTab();
  return h+'</div>';
}
function fmtShort(s){return parseYmd(s).toLocaleDateString('es-MX',{day:'numeric',month:'short'})}
function tripsTable(list){
  return '<div class="tablewrap"><table><thead><tr><th>No.</th><th>Operador</th><th>Tractor</th><th>Nodriza</th><th>Asignación</th><th class="n">Req.</th><th class="n">Total</th><th class="n">Dif.</th><th>Estado</th></tr></thead><tbody>'+
   list.map(function(t){var d=difOf(t);
     return '<tr class="click" data-act="openTrip" data-id="'+esc(t.id)+'"><td>'+esc(t.opNum)+'</td><td>'+esc(t.opName)+(t.verified&&t.invOk===false?' <span class="tag warn" title="El operador contó distinto al sistema">≠ sistema</span>':'')+(t.hasPhoto?' <span class="tag info">foto</span>':'')+(t.comentario?' <span class="tag neutral" title="Dejó un comentario">comentario</span>':'')+'</td><td>'+esc(t.tractor||'—')+'</td><td><b>'+esc(t.nodriza)+'</b></td><td>'+chips(t)+'</td><td class="n">'+t.required+'</td><td class="n">'+(t.total==null?'—':t.total)+'</td><td class="n"><span class="dif '+difCls(d)+'" style="font-size:17px">'+difTxt(d)+'</span></td><td>'+tag(t.status||'asignado')+(t.hasSig?' <span class="tag ok" title="Firmó">firma</span>':'')+'</td></tr>'}).join('')+
   '</tbody></table></div>';
}
function asigTab(){
  var trips=S.trips.slice().sort(function(a,b){return (a.opName||'').localeCompare(b.opName||'')});
  var counts={all:trips.length,asignado:0,aceptado:0,esperando:0,listo:0,confirmado:0};
  trips.forEach(function(t){counts[t.status||'asignado']++});
  var q=(U.f.fText||'').trim().toLowerCase();
  var list=trips.filter(function(t){
    if(U.filter!=='all'&&(t.status||'asignado')!==U.filter)return false;
    return !q||(t.opName+' '+t.opNum+' '+t.nodriza+' '+(t.tractor||'')).toLowerCase().indexOf(q)>=0;
  });
  var h='<div class="row sp" style="margin-bottom:6px">'+dateBar()+'<div class="row">'+(S.isAdmin?'<button class="btn primary" data-act="openImport">Cargar asignación</button>':'')+
    (trips.length?'<button class="btn" data-act="exportCsv">Exportar CSV</button>':'')+(S.isAdmin&&trips.length?'<button class="btn danger" data-act="delDay">Borrar el día</button>':'')+'</div></div>';
  if(!trips.length)return h+'<div class="card empty"><h3>No hay asignaciones el '+esc(fmtDate(S.date))+'</h3>'+(S.isAdmin?'<p>Pega la lista del día (con tractor, número de empleado o nombre) y revísala antes de cargarla.</p><button class="btn primary" data-act="openImport">Cargar asignación</button>':'<p style="margin:0">Cuando se cargue la asignación de este día aparecerá aquí.</p>')+'</div>';
  h+='<div class="row sp" style="margin-bottom:10px"><div class="seg">'+
    [['all','Todos'],['asignado','Sin aceptar'],['aceptado','Contando'],['esperando','Esperando bandas'],['listo','Listos'],['confirmado','Confirmados']].map(function(x){
      return '<button class="btn" data-act="filter" data-id="'+x[0]+'" aria-pressed="'+(U.filter===x[0])+'">'+x[1]+' '+counts[x[0]]+'</button>'}).join('')+
    '</div><input type="text" id="fText" style="max-width:260px" placeholder="Buscar nombre, número, tractor o nodriza" value="'+esc(U.f.fText||'')+'"></div>';
  h+=tripsTable(list);
  if(!list.length)h+='<div class="empty">Ningún viaje coincide con el filtro.</div>';
  return h;
}
/* Formulario de aprobación/rechazo de una solicitud (se usa en Solicitudes y en el detalle del viaje) */
function resolveForm(t,r){
  var k=r.id,n=U.f['apN_'+k]!=null?U.f['apN_'+k]:r.n,src=U.f['apSrc_'+k]||'almacen';
  return '<div class="note warn stack" style="margin-bottom:10px"><div><b>'+esc(t.opName)+'</b> pide <b>'+r.n+'</b>'+(r.note?' — '+esc(r.note):'')+' <small>('+fmtTime(r.ts)+')</small></div>'+
    '<div class="grid2"><label class="f"><span>Cantidad a entregar</span><input type="number" min="1" id="apN_'+k+'" value="'+esc(n)+'"></label>'+
    '<label class="f"><span>Sale de</span><select id="apSrc_'+k+'">'+srcOpts(src)+'</select></label>'+
    (src==='nodriza'?'<label class="f"><span>Código de la nodriza</span><input type="text" id="apCode_'+k+'" list="nodList" value="'+esc(U.f['apCode_'+k]||'')+'" placeholder="AT2301"></label>':'')+
    '<label class="f"><span>Nota / motivo (opcional)</span><input type="text" id="apNote_'+k+'" value="'+esc(U.f['apNote_'+k]||'')+'"></label></div>'+
    '<div class="row"><button class="btn ok" data-act="approve" data-id="'+esc(t.id)+'" data-rid="'+esc(k)+'">Aprobar y actualizar inventario</button>'+
    '<button class="btn danger" data-act="reject" data-id="'+esc(t.id)+'" data-rid="'+esc(k)+'">Rechazar</button></div>'+
    '<div class="sub" style="color:inherit;opacity:.85">Al aprobar se descuenta del origen y se suma a la nodriza '+esc(t.nodriza)+' ('+(typeof t.nodNow==='number'?'ahora tiene '+t.nodNow:'sin dato')+').</div></div>';
}
function solTab(){
  if(!S.pending.length)return '<div class="card empty"><h3>No hay solicitudes pendientes</h3><p style="margin:0">Cuando un operador pida bandas, aparecerá aquí para que la apruebes o rechaces.</p></div>';
  var list=S.pending.slice().sort(function(a,b){return (a.fecha+a.opName).localeCompare(b.fecha+b.opName)});
  return list.map(function(t){
    return '<div class="card"><div class="row sp" style="margin-bottom:8px"><div class="row"><b style="font-family:var(--font-head);font-size:24px">'+esc(t.nodriza)+'</b>'+chips(t)+
      '</div><div class="row"><span class="sub">'+esc(t.opName)+' ('+esc(t.opNum)+') · '+esc(fmtDate(t.fecha))+'</span><button class="btn sm" data-act="openTrip" data-id="'+esc(t.id)+'">Ver viaje</button></div></div>'+
      '<div class="sub" style="margin-bottom:8px">Sistema decía <b>'+(t.sysInv==null?'—':t.sysInv)+'</b> · operador contó <b>'+(t.base==null?'—':t.base)+'</b> · total actual <b>'+(t.total==null?'—':t.total)+'</b> de <b>'+t.required+'</b> requeridas'+(t.hasPhoto?' · <a href="'+photoUrl(t,'foto')+'" target="_blank" rel="noopener">ver foto</a>':'')+'</div>'+
      pendingReqs(t).map(function(r){return resolveForm(t,r)}).join('')+'</div>';
  }).join('');
}

/* ---------- PANEL ADMINISTRATIVO ---------- */
function rankRows(list,opts){
  if(!list.length)return '<div class="rank"><div class="empty" style="padding:22px 16px">'+opts.empty+'</div></div>';
  var max=Math.max.apply(null,list.map(opts.val).concat([1]));
  return '<div class="rank">'+list.map(function(o,i){
    var v=opts.val(o);
    return '<button class="rank-row" data-act="rankDet" data-por="'+opts.por+'" data-id="'+esc(opts.key(o))+'" data-nm="'+esc(opts.name(o))+'" title="Ver el detalle de solicitudes"><div class="pos">'+(i+1)+'</div><div class="who"><b>'+esc(opts.name(o))+'</b><small>'+opts.sub(o)+'</small></div>'+
      '<div class="bar" role="img" aria-label="'+v+'"><i style="width:'+Math.round(v/max*100)+'%"></i></div><div class="val">'+v+'<small>'+opts.unit+'</small></div><div class="go" aria-hidden="true">&rsaquo;</div></button>'}).join('')+'</div>';
}
function panelTab(){
  var P=U.statsPreset,st=S.stats;
  var h='<div class="row sp no-print" style="margin-bottom:12px"><div class="seg">'+
    [['4w','4 semanas'],['8w','8 semanas'],['12w','12 semanas'],['mes','Este mes'],['anio','Este año'],['custom','Otro periodo']].map(function(x){
      return '<button class="btn" data-act="statsPreset" data-id="'+x[0]+'" aria-pressed="'+(P===x[0])+'">'+x[1]+'</button>'}).join('')+'</div>'+
    '<div class="row"><button class="btn" data-act="exportStatsCsv"'+(st?'':' disabled')+'>Exportar CSV</button><button class="btn" data-act="printPage">Imprimir</button></div></div>';
  if(P==='custom'){
    var r=statsRange();
    h+='<div class="row no-print" style="margin-bottom:12px"><label class="f"><span>Desde</span><input type="date" id="stDesde" value="'+esc(U.f.stDesde||r.desde)+'"></label><label class="f"><span>Hasta</span><input type="date" id="stHasta" value="'+esc(U.f.stHasta||r.hasta)+'"></label><button class="btn primary" data-act="statsApply" style="align-self:flex-end">Ver</button></div>';
  }
  if(!st)return h+'<div class="loading">Calculando estadísticas…</div>';
  var k=st.kpis,pct=k.viajes?Math.round(k.viajesConSolicitud/k.viajes*100):0;
  h+='<p class="sub" style="margin:0 0 10px">Del '+esc(fmtShort(st.desde))+' al '+esc(fmtShort(st.hasta))+' · "Pidió" cuenta las solicitudes que no se cancelaron; "se le pusieron" son las bandas que se entregaron a la nodriza.</p>';
  h+='<div class="kpis">'+kpi('Viajes',k.viajes,k.firmados+' firmados')+kpi('Solicitudes de bandas',k.solicitudes,pct+'% de los viajes')+kpi('Bandas pedidas',k.bandasPedidas,'por operadores')+kpi('Bandas puestas',k.bandasPuestas,'entregadas a nodrizas')+
     kpi('Operadores',k.operadores,k.nodrizas+' nodrizas')+kpi('Conteos distintos',k.difs,'operador ≠ sistema')+'</div>';

  var opsReq=st.operadores.filter(function(o){return o.bandasPedidas>0});
  h+='<div class="sec-title"><h3>Quién ha pedido más bandas</h3><span class="sub">'+opsReq.length+' de '+st.operadores.length+' operadores han pedido · toca uno para ver cada solicitud</span></div>';
  h+=rankRows(opsReq.slice(0,U.f.opAll?100:10),{por:'op',key:function(o){return o.opNum},empty:'Nadie pidió bandas en este periodo.',val:function(o){return o.bandasPedidas},unit:'bandas',name:function(o){return o.opName},
    sub:function(o){return esc(o.opNum)+(o.tractor?' · tractor '+esc(o.tractor):'')+' · '+o.solicitudes+(o.solicitudes===1?' solicitud':' solicitudes')+' en '+o.viajes+(o.viajes===1?' viaje':' viajes')+(o.ultimaSolicitud?' · última '+esc(fmtShort(o.ultimaSolicitud)):'')}});
  if(opsReq.length>10)h+='<div class="no-print" style="margin:-6px 0 14px"><button class="btn sm" data-act="toggleOpAll">'+(U.f.opAll?'Ver solo los 10 primeros':'Ver los '+opsReq.length)+'</button></div>';

  var nodReq=st.nodrizas.filter(function(o){return o.bandasPuestas>0||o.bandasPedidas>0});
  h+='<div class="sec-title"><h3>Nodrizas a las que más bandas se les han puesto</h3><span class="sub">'+nodReq.length+' de '+st.nodrizas.length+'</span></div>';
  h+=rankRows(nodReq.slice(0,10),{por:'nod',key:function(o){return o.codigo},empty:'A ninguna nodriza se le pusieron bandas en este periodo.',val:function(o){return o.bandasPuestas},unit:'puestas',name:function(o){return o.codigo},
    sub:function(o){return 'pidieron '+o.bandasPedidas+' · '+o.solicitudes+(o.solicitudes===1?' solicitud':' solicitudes')+' en '+o.viajes+(o.viajes===1?' viaje':' viajes')+(o.bandasAhora!=null?' · ahora tiene '+o.bandasAhora:'')}});

  var maxW=Math.max.apply(null,st.semanas.map(function(w){return Math.max(w.bandasPedidas,w.bandasPuestas)}).concat([1]));
  h+='<div class="sec-title"><h3>Por semana</h3><div class="legend"><span>Pedidas</span><span class="l2">Puestas</span></div></div>';
  if(!st.semanas.length)h+='<div class="card empty"><p style="margin:0">Sin viajes en este periodo.</p></div>';
  else h+='<div class="tablewrap"><table style="min-width:640px"><thead><tr><th>Semana</th><th class="n">Viajes</th><th class="n">Solicitudes</th><th class="n">Pedidas</th><th class="n">Puestas</th><th style="width:30%">Comparativo</th></tr></thead><tbody>'+
    st.semanas.slice().reverse().map(function(w){
      return '<tr><td><b>'+esc(fmtShort(w.inicio))+'</b> – '+esc(fmtShort(w.fin))+'</td><td class="n">'+w.viajes+'</td><td class="n">'+w.solicitudes+'</td><td class="n">'+w.bandasPedidas+'</td><td class="n">'+w.bandasPuestas+'</td>'+
       '<td><div class="bar" style="margin-bottom:3px"><i style="width:'+Math.round(w.bandasPedidas/maxW*100)+'%"></i></div><div class="bar"><i class="b2" style="width:'+Math.round(w.bandasPuestas/maxW*100)+'%"></i></div></td></tr>'}).join('')+'</tbody></table></div>';

  var dd=(st.dias||[]).slice().reverse();
  h+='<div class="sec-title"><h3>Por día</h3><span class="sub">toca un día para ver sus viajes, firmas y comentarios</span></div>';
  if(!dd.length)h+='<div class="card empty"><p style="margin:0">Sin viajes en este periodo.</p></div>';
  else h+='<div class="tablewrap"><table style="min-width:640px"><thead><tr><th>Día</th><th class="n">Viajes</th><th class="n">Firmados</th><th class="n">Solicitudes</th><th class="n">Pedidas</th><th class="n">Puestas</th><th class="n">Conteos &ne;</th></tr></thead><tbody>'+
    dd.slice(0,U.f.diasAll?400:14).map(function(w){
      return '<tr class="click" data-act="panelDay" data-id="'+esc(w.fecha)+'"><td><b>'+esc(fmtDate(w.fecha))+'</b></td><td class="n">'+w.viajes+'</td><td class="n">'+w.firmados+'/'+w.viajes+'</td><td class="n">'+w.solicitudes+'</td><td class="n">'+w.bandasPedidas+'</td><td class="n">'+w.bandasPuestas+'</td><td class="n">'+(w.difs||0)+'</td></tr>'}).join('')+'</tbody></table></div>'+
    (dd.length>14?'<div class="no-print" style="margin:-6px 0 14px"><button class="btn sm" data-act="toggleDias">'+(U.f.diasAll?'Ver solo los últimos 14 días':'Ver los '+dd.length+' días')+'</button></div>':'');

  h+='<div class="sec-title"><h3>Detalle por operador</h3><button class="btn sm no-print" data-act="toggleOpTable">'+(U.f.opTable?'Ocultar':'Mostrar')+' tabla completa</button></div>';
  if(U.f.opTable)h+='<div class="tablewrap"><table style="min-width:820px"><thead><tr><th>No.</th><th>Operador</th><th>Tractor</th><th class="n">Viajes</th><th class="n">Solicitudes</th><th class="n">Pedidas</th><th class="n">Puestas</th><th class="n">% con solicitud</th><th class="n">Conteos ≠</th></tr></thead><tbody>'+
    st.operadores.map(function(o){return '<tr><td>'+esc(o.opNum)+'</td><td>'+esc(o.opName)+'</td><td>'+esc(o.tractor||'—')+'</td><td class="n">'+o.viajes+'</td><td class="n">'+o.solicitudes+'</td><td class="n">'+o.bandasPedidas+'</td><td class="n">'+o.bandasPuestas+'</td><td class="n">'+(o.viajes?Math.round(o.viajesConSolicitud/o.viajes*100):0)+'%</td><td class="n">'+o.difs+'</td></tr>'}).join('')+'</tbody></table></div>';
  return h;
}
function notesHtml(list){
  if(!list.length)return '<div class="card empty" style="padding:22px 16px"><p style="margin:0">Sin comentarios este día.</p></div>';
  return list.map(function(n){
    return '<div class="comment"><small><b>'+esc(n.opName)+'</b> · '+esc(n.nodriza||'')+' · '+esc(n.tipo||'')+' · '+esc(n.de||'')+(n.ts?' · '+fmtTime(n.ts):'')+'</small>'+esc(n.texto||'')+
      ' <button class="btn sm no-print" data-act="openTrip" data-id="'+esc(n.tripId)+'">Ver viaje</button></div>'}).join('');
}
function calTab(){
  var mes=calMes(),y=+mes.slice(0,4),m=+mes.slice(5,7);
  var first=new Date(y,m-1,1),off=(first.getDay()+6)%7,days=new Date(y,m,0).getDate(),today=todayStr();
  var dias=(S.cal&&S.cal.mes===mes)?S.cal.dias:{};
  var title=first.toLocaleDateString('es-MX',{month:'long',year:'numeric'});
  var h='<div class="row sp no-print" style="margin-bottom:10px"><div class="row"><button class="btn icon" data-act="calPrev" aria-label="Mes anterior">‹</button><h3 class="cap" style="font-size:22px;min-width:190px;text-align:center">'+esc(title)+'</h3><button class="btn icon" data-act="calNext" aria-label="Mes siguiente">›</button>'+
    (mes!==today.slice(0,7)?'<button class="btn" data-act="calToday">Hoy</button>':'')+'</div><div class="legend plain" style="margin:0"><span class="dot ok">firmados</span><span class="dot warn">pendientes</span><span class="dot info">bandas puestas</span><span class="dot bad">conteo ≠ sistema</span></div></div>';
  h+='<div class="cal"><div class="cal-head">'+['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].map(function(d){return '<div>'+d+'</div>'}).join('')+'</div><div class="cal-grid">';
  for(var i=0;i<off;i++)h+='<div class="cal-cell out"></div>';
  for(var d=1;d<=days;d++){
    var ymdS=mes+'-'+pad(d),x=dias[ymdS];
    h+='<button class="cal-cell'+(ymdS===today?' today':'')+(ymdS===S.date?' sel':'')+'" data-act="calDay" data-id="'+ymdS+'" aria-label="'+esc(fmtDate(ymdS))+(x?', '+x.viajes+' viajes':', sin viajes')+'"><span class="dn">'+d+'</span>';
    if(x){
      h+='<span class="m1">'+x.viajes+' <small>viajes</small></span><span class="dots">'+
        '<span class="dot ok" title="Firmados">'+x.firmados+'/'+x.viajes+'</span>'+
        (x.pendientes?'<span class="dot warn" title="Solicitudes pendientes">'+x.pendientes+' pend.</span>':'')+
        (x.bandasPuestas?'<span class="dot info" title="Bandas puestas">+'+x.bandasPuestas+'</span>':'')+
        (x.difs?'<span class="dot bad" title="Conteo distinto al sistema">≠'+x.difs+'</span>':'')+'</span>'+
        (x.solicitudes?'<span class="m2">'+x.solicitudes+' solic. · '+x.bandasPedidas+' pedidas'+(x.comentarios?' · '+x.comentarios+' coment.':'')+'</span>':(x.comentarios?'<span class="m2">'+x.comentarios+' coment.</span>':''));
    }
    h+='</button>';
  }
  var rest=(7-((off+days)%7))%7;for(i=0;i<rest;i++)h+='<div class="cal-cell out"></div>';
  h+='</div></div>';

  var list=S.trips.slice().sort(function(a,b){return (a.opName||'').localeCompare(b.opName||'')});
  h+='<div class="sec-title"><h3 class="cap">'+esc(fmtDate(S.date))+'</h3><div class="row no-print">'+(list.length?'<button class="btn" data-act="exportCsv">Exportar CSV</button>':'')+'<button class="btn" data-act="printPage">Imprimir</button>'+(S.isAdmin?'<button class="btn" data-act="goAsig">Abrir en Asignaciones</button>':'')+'</div></div>';
  if(!list.length)h+='<div class="card empty"><p style="margin:0">No hay viajes este día.</p></div>';
  else{
    var sig=list.filter(function(t){return t.firmado}).length,pend=list.reduce(function(n,t){return n+pendingReqs(t).length},0);
    h+='<p class="sub" style="margin:0 0 8px">'+list.length+' viajes · '+sig+' firmados'+(pend?' · '+pend+' solicitud(es) pendiente(s)':'')+'. Toca un viaje para ver su firma, foto y comentarios.</p>'+tripsTable(list);
  }
  h+='<div class="sec-title"><h3>Comentarios del día</h3><span class="sub">'+S.dayNotes.length+'</span></div>'+notesHtml(S.dayNotes);
  return h;
}

function invTotals(){
  var sum=0,zeros=0,stale=0,last=0,lastBy='';
  Object.keys(S.nods).forEach(function(c){var x=S.nods[c];sum+=x.bandas||0;if(!x.bandas)zeros++;if(isStale(x.updated))stale++;if((x.updated||0)>last){last=x.updated;lastBy=x.by||''}});
  var al=S.stock.almacen||0,of=S.stock.oficina||0;
  [S.stockTs.almacen,S.stockTs.oficina].forEach(function(t){if(t&&t>last){last=t;lastBy=''}});
  return {sum:sum,zeros:zeros,stale:stale,last:last,lastBy:lastBy,al:al,of:of,all:sum+al+of,n:Object.keys(S.nods).length};
}
function kpi(l,v,sm){return '<div class="kpi"><span>'+l+'</span><b>'+v+'</b>'+(sm?'<small>'+sm+'</small>':'')+'</div>'}
function invTab(){
  var T=invTotals(),q=(U.f.nodQ||'').trim().toUpperCase();
  var codes=Object.keys(S.nods).filter(function(c){return !q||c.indexOf(q)>=0});
  if(U.invSort==='old')codes.sort(function(a,b){return (S.nods[a].updated||0)-(S.nods[b].updated||0)});
  else if(U.invSort==='low')codes.sort(function(a,b){return (S.nods[a].bandas||0)-(S.nods[b].bandas||0)});
  else codes.sort();
  var vals=Object.keys(S.nods).map(function(c){return S.nods[c].bandas||0}).sort(function(x,y){return x-y}),maxN=Math.max(vals[Math.floor(vals.length*0.9)]||1,1),perN=maxN;
  var h='<div class="kpis">'+kpi('Total de bandas',T.all,'nodrizas + almacén + oficina')+kpi('En nodrizas',T.sum,T.n+' nodrizas'+(T.zeros?' · '+T.zeros+' en 0':''))+
    kpi('Almacén',S.stock.almacen==null?'—':S.stock.almacen,S.stockTs.almacen?'actualizado '+ago(S.stockTs.almacen):'')+kpi('Oficina',S.stock.oficina==null?'—':S.stock.oficina,S.stockTs.oficina?'actualizado '+ago(S.stockTs.oficina):'')+
    kpi('Última actualización',T.last?'<span style="font-size:22px;line-height:1.3;display:block;padding:5px 0">'+ago(T.last)+'</span>':'—',T.last?fmtTime(T.last)+(T.lastBy?' · '+esc(T.lastBy):''):'')+'</div>';

  var hMov='<div class="grid2" style="margin-bottom:14px">'+
   '<div class="card stack"><h3>Entradas y salidas de almacén / oficina</h3>'+
     '<div class="grid2"><label class="f"><span>Ubicación</span><select id="smUb"><option value="almacen"'+(U.f.smUb!=='oficina'?' selected':'')+'>Almacén</option><option value="oficina"'+(U.f.smUb==='oficina'?' selected':'')+'>Oficina</option></select></label>'+
     '<label class="f"><span>Movimiento</span><select id="smModo">'+[['entrada','Entrada (+)'],['salida','Salida (−)'],['fijar','Fijar total (conteo físico)']].map(function(o){return '<option value="'+o[0]+'"'+((U.f.smModo||'entrada')===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select></label>'+
     '<label class="f"><span>Cantidad</span><input type="number" min="0" id="smN" value="'+esc(U.f.smN||'')+'"></label>'+
     '<label class="f"><span>Motivo (opcional)</span><input type="text" id="smNota" value="'+esc(U.f.smNota||'')+'" placeholder="Ej. compra, merma"></label></div>'+
     '<button class="btn primary" data-act="saveStockMov">Registrar movimiento</button></div>'+
   '<div class="card stack"><h3>Mover bandas</h3><p class="sub" style="margin:0">Pasa bandas entre almacén, oficina y nodrizas. Se descuenta y se suma al mismo tiempo.</p>'+
     '<div class="grid2"><label class="f"><span>Sale de</span><select id="trO">'+moveOpts(U.f.trO||'almacen')+'</select></label>'+
     '<label class="f"><span>Entra a</span><select id="trD">'+moveOpts(U.f.trD||'nodriza')+'</select></label>'+
     ((U.f.trO||'almacen')==='nodriza'?'<label class="f"><span>Nodriza de origen</span><input type="text" id="trOC" list="nodList" value="'+esc(U.f.trOC||'')+'" placeholder="AT2301"></label>':'')+
     ((U.f.trD||'nodriza')==='nodriza'?'<label class="f"><span>Nodriza de destino</span><input type="text" id="trDC" list="nodList" value="'+esc(U.f.trDC||'')+'" placeholder="AC2406"></label>':'')+
     '<label class="f"><span>Cantidad</span><input type="number" min="1" id="trN" value="'+esc(U.f.trN||'')+'"></label>'+
     '<label class="f"><span>Nota (opcional)</span><input type="text" id="trNota" value="'+esc(U.f.trNota||'')+'"></label></div>'+
     '<button class="btn primary" data-act="doTransfer">Mover</button></div></div>';

  var hAdd='<div class="grid2" style="margin-bottom:14px">'+
   '<div class="card stack"><h3>Agregar nodriza</h3><div class="grid2"><label class="f"><span>Código</span><input type="text" id="anCod" value="'+esc(U.f.anCod||'')+'" placeholder="AC2410"></label>'+
     '<label class="f"><span>Bandas que trae</span><input type="number" min="0" id="anN" value="'+esc(U.f.anN||'')+'"></label></div>'+
     '<button class="btn" data-act="addNod">Agregar</button>'+
     (S.isAdmin?'<label class="f"><span>Bandas por unidad (Bronco o Maverick)</span><input type="number" min="1" id="cfgPer" value="'+(U.f.cfgPer!=null?esc(U.f.cfgPer):S.cfg.perUnit)+'"></label>'+
     '<button class="btn" data-act="savePer">Guardar</button><p class="sub" style="margin:0">Solo afecta las asignaciones que cargues desde ahora.</p>':'<p class="sub" style="margin:0">Cada Bronco o Maverick cuenta como '+S.cfg.perUnit+' bandas.</p>')+'</div>'+
   '<div class="card stack"><h3>Cargar inventario (pegar)</h3><p class="sub" style="margin:0">Una línea por nodriza: código y total. También acepta ALMACEN y OFICINA. Crea las que no existan y deja registro de cada cambio.</p>'+
     '<textarea id="invText" placeholder="AB2401&#9;26&#10;AB2402&#9;24&#10;ALMACEN&#9;125">'+esc(U.f.invText||'')+'</textarea><button class="btn primary" data-act="doInvImport">Cargar inventario</button>'+
     (U.invMsg?'<div class="note ok">Se procesaron '+U.invMsg.n+' nodrizas'+(U.invMsg.r?': '+U.invMsg.r.nuevas+' nuevas, '+U.invMsg.r.cambiadas+' con cantidad corregida, '+U.invMsg.r.iguales+' sin cambio':'')+(U.invMsg.stock?' y el stock':'')+'.'+(U.invMsg.bad.length?' No se reconocieron '+U.invMsg.bad.length+' líneas.':'')+'</div>':'')+'</div></div>';

  var iv=U.invView||'nod';
  h+='<div class="subnav no-print" role="tablist">'+[['nod','Nodrizas'],['mover','Almacén y movimientos'],['cargar','Agregar y cargar']].map(function(x){return '<button role="tab" data-act="invView" data-id="'+x[0]+'" aria-selected="'+(iv===x[0])+'">'+x[1]+'</button>'}).join('')+'</div>';
  if(iv==='mover')return h+hMov;
  if(iv==='cargar')return h+hAdd;

  h+='<div class="toolbar"><h3 style="font-size:19px">Bandas por nodriza <span class="sub">'+T.n+' registradas'+(T.zeros?' · '+T.zeros+' en 0':'')+(T.stale?' · '+T.stale+' sin actualizar hace +'+STALE_DAYS+' días':'')+'</span></h3>'+
    '<select id="invSort" style="width:auto">'+[['cod','Ordenar: código'],['old','Más sin actualizar primero'],['low','Menos bandas primero']].map(function(o){return '<option value="'+o[0]+'"'+(U.invSort===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select>'+
    '<input type="text" id="nodQ" style="max-width:200px" placeholder="Buscar nodriza" value="'+esc(U.f.nodQ||'')+'"><button class="btn" data-act="exportInvCsv">Exportar CSV</button></div>';
  if(!codes.length)h+='<div class="card empty"><h3>Sin nodrizas'+(q?' que coincidan':' registradas')+'</h3><p style="margin:0">Agrega una arriba o carga el inventario inicial.</p></div>';
  else h+='<div class="tablewrap"><table style="min-width:700px"><thead><tr><th>Nodriza</th><th class="n">Bandas</th><th>Nivel</th><th>Última actualización</th><th>Por</th><th></th></tr></thead><tbody>'+
    codes.map(function(c){var x=S.nods[c];
      return '<tr'+(isStale(x.updated)?' class="stale"':'')+'><td><b>'+esc(c)+'</b></td><td class="n"><input type="number" min="0" style="width:90px;text-align:right" id="nod_'+esc(c)+'" data-nod="'+esc(c)+'" value="'+(x.bandas==null?'':x.bandas)+'"></td>'+
        '<td><div class="lvl'+(x.bandas?'':' zero')+'">'+(x.bandas?'<div class="prog'+((x.bandas<perN*0.5)?' low':'')+'"><i style="width:'+Math.min(100,Math.round((x.bandas||0)/maxN*100))+'%"></i></div>':'<span class="agotada">Sin bandas</span>')+'</div></td>'+
        '<td>'+(x.updated?esc(ago(x.updated))+' <span class="sub">· '+fmtTime(x.updated)+'</span>':'<span class="sub">sin fecha</span>')+'</td><td class="sub">'+esc(x.by||'')+'</td>'+
        '<td><button class="btn sm" data-act="nodHist" data-id="'+esc(c)+'">Historial</button> '+(S.isAdmin?'<button class="btn sm danger" data-act="delNod" data-id="'+esc(c)+'">Eliminar</button>':'')+'</td></tr>'}).join('')+'</tbody></table></div>';
  if(S.isAdmin)h+='<div class="row" style="margin-top:16px"><button class="btn" data-act="clearNotifs">Borrar avisos leídos</button></div>';
  return h;
}
function moveOpts(sel){return [['almacen','Almacén'],['oficina','Oficina'],['nodriza','Una nodriza']].map(function(o){return '<option value="'+o[0]+'"'+(sel===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')}

function movTable(list,showWhere){
  if(!list.length)return '<div class="card empty" style="margin:0"><p style="margin:0">Sin movimientos en este periodo.</p></div>';
  return '<div class="tablewrap"><table style="min-width:820px"><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Tipo</th>'+(showWhere?'<th>Dónde</th>':'')+'<th class="n">Antes</th><th class="n">Después</th><th class="n">Dif.</th><th>Motivo</th></tr></thead><tbody>'+
    list.map(function(m){return '<tr><td>'+fmtTime(m.ts)+'</td><td>'+esc(m.usuario)+'</td><td>'+esc(MOVTIPO[m.tipo]||m.tipo)+'</td>'+(showWhere?'<td>'+movWhere(m)+'</td>':'')+
      '<td class="n">'+(m.antes==null?'—':m.antes)+'</td><td class="n">'+m.despues+'</td><td class="n"><span class="dif '+(m.diferencia<0?'neg':m.diferencia>0?'pos':'zero')+'" style="font-size:17px">'+(m.diferencia>0?'+':'')+m.diferencia+'</span></td><td class="sub">'+esc(m.motivo)+'</td></tr>'}).join('')+'</tbody></table></div>';
}
function repTab(){
  repDefaults();var T=invTotals();
  var staleList=Object.keys(S.nods).filter(function(c){return isStale(S.nods[c].updated)}).sort(function(a,b){return (S.nods[a].updated||0)-(S.nods[b].updated||0)});
  var h='<div class="kpis">'+kpi('Total de bandas',T.all,'nodrizas + almacén + oficina')+kpi('En nodrizas',T.sum,T.n+' nodrizas')+kpi('Almacén',S.stock.almacen==null?'—':S.stock.almacen,S.stockTs.almacen?ago(S.stockTs.almacen):'')+kpi('Oficina',S.stock.oficina==null?'—':S.stock.oficina,S.stockTs.oficina?ago(S.stockTs.oficina):'')+
    kpi('Última actualización',T.last?'<span style="font-size:22px;line-height:1.3;display:block;padding:5px 0">'+ago(T.last)+'</span>':'—',T.last?fmtTime(T.last)+(T.lastBy?' · '+esc(T.lastBy):''):'')+kpi('Sin actualizar +'+STALE_DAYS+' días',T.stale,T.zeros+' nodrizas en 0')+'</div>';
  h+='<div class="card stack"><div class="row sp"><h3>Filtros del reporte</h3><div class="row"><button class="btn" data-act="exportInvCsv">Exportar inventario CSV</button><button class="btn" data-act="exportMovCsv"'+(S.rep?'':' disabled')+'>Exportar movimientos CSV</button></div></div>'+
    '<div class="grid2"><label class="f"><span>Desde</span><input type="date" id="repDesde" value="'+esc(U.f.repDesde)+'"></label><label class="f"><span>Hasta</span><input type="date" id="repHasta" value="'+esc(U.f.repHasta)+'"></label>'+
    '<label class="f"><span>Tipo de movimiento</span><select id="repTipo"><option value="todos">Todos</option>'+Object.keys(MOVTIPO).map(function(k){return '<option value="'+k+'"'+(U.f.repTipo===k?' selected':'')+'>'+MOVTIPO[k]+'</option>'}).join('')+'</select></label>'+
    '<label class="f"><span>Nodriza (opcional)</span><input type="text" id="repCod" list="nodList" value="'+esc(U.f.repCod||'')+'" placeholder="Todas"></label></div>'+
    '<div><button class="btn primary" data-act="loadRep">Actualizar reporte</button></div></div>';
  var staleH='';
  if(staleList.length){
    staleH='<h3 style="font-size:19px;margin:6px 0 8px">Nodrizas sin actualizar hace más de '+STALE_DAYS+' días <span class="sub">'+staleList.length+'</span></h3><div class="tablewrap"><table style="min-width:420px"><thead><tr><th>Nodriza</th><th class="n">Bandas</th><th>Última actualización</th><th>Por</th></tr></thead><tbody>'+
      staleList.slice(0,60).map(function(c){var x=S.nods[c];return '<tr class="stale"><td><b>'+esc(c)+'</b></td><td class="n">'+(x.bandas==null?'—':x.bandas)+'</td><td>'+(x.updated?esc(ago(x.updated))+' <span class="sub">· '+fmtTime(x.updated)+'</span>':'nunca')+'</td><td class="sub">'+esc(x.by||'')+'</td></tr>'}).join('')+'</tbody></table></div>';
  }
  if(!S.rep)return h+'<div class="card empty"><p style="margin:0">Cargando el reporte…</p></div>'+staleH;
  h+='<h3 style="font-size:19px;margin:6px 0 8px">Diferencias de conteo (operador vs. sistema) <span class="sub">'+S.rep.disc.length+'</span></h3>';
  if(!S.rep.disc.length)h+='<div class="card empty" style="margin-bottom:14px"><p style="margin:0">Ninguna en este periodo.</p></div>';
  else h+='<div class="tablewrap"><table style="min-width:640px"><thead><tr><th>Fecha</th><th>Operador</th><th>Nodriza</th><th class="n">Sistema</th><th class="n">Operador</th><th class="n">Dif.</th><th>Foto</th></tr></thead><tbody>'+
    S.rep.disc.map(function(x){var d=x.sys==null?null:x.base-x.sys;
      return '<tr><td>'+esc(x.fecha)+'</td><td>'+esc(x.opName)+' <span class="sub">('+esc(x.opNum)+')</span></td><td><b>'+esc(x.nodriza)+'</b></td><td class="n">'+(x.sys==null?'—':x.sys)+'</td><td class="n">'+x.base+'</td><td class="n"><span class="dif '+difCls(d)+'" style="font-size:17px">'+difTxt(d)+'</span></td>'+
        '<td>'+(x.hasPhoto?'<a href="/foto.php?id='+encodeURIComponent(x.id)+'&t=foto" target="_blank" rel="noopener"><img class="thumb" loading="lazy" alt="Foto de la nodriza" src="/foto.php?id='+encodeURIComponent(x.id)+'&t=foto"></a>':'<span class="sub">sin foto</span>')+'</td></tr>'}).join('')+'</tbody></table></div>';
  h+='<h3 style="font-size:19px;margin:6px 0 8px">Movimientos de inventario <span class="sub">'+S.rep.mov.length+(S.rep.mov.length>=1000?' (mostrando los 1000 más recientes)':'')+'</span></h3>'+movTable(S.rep.mov,true)+staleH;
  return h;
}

function usrsTab(){
  var rolOpts=function(sel){return [['operador','Operador'],['administrativo','Administrativo'],['admin','Administrador']].map(function(o){return '<option value="'+o[0]+'"'+(sel===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')};
  var h='<div class="grid2" style="margin-bottom:14px">';
  h+='<div class="card stack"><h3>Crear usuario</h3>'+
     '<label class="f"><span>Número de empleado (usuario para entrar)</span><input type="text" id="nuNum" value="'+esc(U.f.nuNum||'')+'" placeholder="Ej. 134432"></label>'+
     '<label class="f"><span>Nombre completo</span><input type="text" id="nuNom" value="'+esc(U.f.nuNom||'')+'" placeholder="Ej. Juan Pérez"></label>'+
     '<div class="grid2"><label class="f"><span>Rol</span><select id="nuRol">'+rolOpts(U.f.nuRol||'operador')+'</select></label>'+
     '<label class="f"><span>Tractor habitual (opcional)</span><input type="text" id="nuTr" value="'+esc(U.f.nuTr||'')+'" placeholder="Ej. 24063"></label></div>'+
     '<label class="f"><span>Contraseña inicial (deberá cambiarla al entrar)</span><input type="text" id="nuPwd" value="'+esc(U.f.nuPwd||'')+'" placeholder="Mínimo 6 caracteres"></label>'+
     '<button class="btn primary" data-act="saveUser">Crear usuario</button></div>';
  h+='<div class="card stack"><h3>Carga masiva de operadores</h3>'+
     '<p class="sub" style="margin:0">Pega desde Excel: <b>número de empleado</b>, <b>nombre</b> y, si quieres, el <b>tractor</b> al final.</p>'+
     '<div class="note info">La contraseña inicial es el mismo número de empleado y el sistema les pedirá cambiarla en su primer acceso.</div>'+
     '<textarea id="nuBulk" placeholder="134432    JESUS MANUEL CANTU MONTES    25023&#10;133551    LIZETH ESTENIA CANTORAL MIRANDA    23015">'+esc(U.f.nuBulk||'')+'</textarea>'+
     '<button class="btn primary" data-act="saveBulkUsers">Cargar masivo</button></div></div>';

  var users=S.users;
  h+='<div class="sec-title"><h3>Usuarios <span class="sub">'+(users?users.length:'')+'</span></h3><div class="row"><input type="text" id="uQ" style="max-width:240px" placeholder="Buscar nombre, número o tractor" value="'+esc(U.f.uQ||'')+'">'+
     '<button class="btn" data-act="askAllPwd">Pedir cambio de contraseña a todos los operadores</button></div></div>';
  if(!users)return h+'<div class="loading">Cargando usuarios…</div>';
  var q=(U.f.uQ||'').trim().toLowerCase();
  var list=users.filter(function(u){return !q||(u.num+' '+u.nombre+' '+u.tractor).toLowerCase().indexOf(q)>=0});
  h+='<p class="sub" style="margin:0 0 8px">El <b>tractor habitual</b> se usa cuando la lista del día trae solo el nombre o el número de empleado. Si un día cambia de tractor, se corrige en la vista previa al cargar o dentro del viaje.</p>';
  h+='<div class="tablewrap"><table style="min-width:860px"><thead><tr><th>No. empleado</th><th>Nombre</th><th>Rol</th><th>Tractor habitual</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead><tbody>'+
    list.map(function(u){var me=(u.num===S.opNum);
      return '<tr class="userrow"'+(u.estado!=='activo'?' style="opacity:.55"':'')+'><td><b>'+esc(u.num)+'</b></td><td>'+esc(u.nombre)+(u.debe?' <span class="tag warn" title="Debe cambiar su contraseña al entrar">contraseña pendiente</span>':'')+'</td>'+
       '<td><select data-user-rol="'+u.id+'" id="uRol_'+u.id+'" style="min-width:140px"'+(me?' disabled':'')+'>'+rolOpts(u.rol)+'</select></td>'+
       '<td><input type="text" data-user-tractor="'+u.id+'" id="uTr_'+u.id+'" value="'+esc(u.tractor)+'" style="width:110px" placeholder="—" maxlength="20" aria-label="Tractor de '+esc(u.nombre)+'"></td>'+
       '<td class="sub">'+(u.ultimo?esc(ago(u.ultimo)):'nunca')+'</td><td>'+(u.estado==='activo'?'<span class="tag ok">Activo</span>':'<span class="tag neutral">Inactivo</span>')+'</td>'+
       '<td class="row" style="flex-wrap:nowrap"><button class="btn sm" data-act="pwdReset" data-id="'+u.id+'">Contraseña</button>'+(me?'':'<button class="btn sm '+(u.estado==='activo'?'danger':'')+'" data-act="toggleUser" data-id="'+u.id+'">'+(u.estado==='activo'?'Desactivar':'Activar')+'</button>')+'</td></tr>'}).join('')+'</tbody></table></div>';
  if(!list.length)h+='<div class="empty">Nadie coincide con la búsqueda.</div>';
  return h;
}

/* ---------- modales ---------- */
function kv(l,v){return '<div class="kv"><span>'+l+'</span><b>'+v+'</b></div>'}
var METODO={empleado:'por no. de empleado',tractor:'por tractor',nombre:'por nombre'};
function pvRow(r,i){
  var e=r.ed,inf=rowInfo(r);
  var warn=!e.skip&&inf.ready&&(r.estado==='revisar'||inf.cambio||inf.nodNueva||e.crear);
  var cls=e.skip?'skip':(!inf.ready?'error':(warn?'revisar':''));
  var st=e.skip?'<span class="tag neutral">No se carga</span>':(inf.ready?(warn?'<span class="tag warn">Revisar</span>':'<span class="tag ok">Listo</span>'):'<span class="tag bad">Falta: '+esc(inf.miss.join(', '))+'</span>');
  var avisos=(r.avisos||[]).filter(function(a){
    if(/^No se encontró al operador|^Hay más de un/.test(a))return !(e.opNum||e.crear);
    if(/^Operador nuevo/.test(a))return !!e.crear;
    if(/^Cambio de tractor/.test(a))return false;
    if(/^No se encontró el código de la nodriza/.test(a))return !(e.nodriza||'').trim();
    return true;
  });
  if(inf.nodNueva&&!e.skip)avisos.push('La nodriza '+(e.nodriza||'').trim().toUpperCase()+' no está en Inventario: se registrará con 0 bandas (corrígelo después en Inventario).');
  if(inf.cambio)avisos.push('Cambio de tractor: el habitual de este operador es '+inf.hab+' y hoy sale con '+e.tractor+'.');
  var cand=r.candidatos||[],seen={};
  cand.forEach(function(c){seen[c.num]=1});
  var opts='<option value="">— Elegir operador —</option>'+
    (cand.length?'<optgroup label="Posibles">'+cand.map(function(c){return '<option value="'+esc(c.num)+'"'+(e.opNum===c.num&&!e.crear?' selected':'')+'>'+esc(c.num)+' · '+esc(c.nombre)+'</option>'}).join('')+'</optgroup>':'')+
    '<optgroup label="Todos los operadores">'+S.ops.filter(function(o){return !seen[o.num]}).map(function(o){return '<option value="'+esc(o.num)+'"'+(e.opNum===o.num&&!e.crear?' selected':'')+'>'+esc(o.num)+' · '+esc(o.nombre)+(o.tractor?' · '+esc(o.tractor):'')+'</option>'}).join('')+'</optgroup>'+
    '<option value="__new"'+(e.crear?' selected':'')+'>＋ Crear operador nuevo…</option>';
  return '<div class="pv-row '+cls+'"><div class="pv-top"><span class="sub">'+(i+1)+'.</span>'+st+chips(r)+(r.metodo?'<span class="sub">Identificado '+METODO[r.metodo]+'</span>':'')+'</div>'+
    '<div class="sub" style="font-size:12.5px;word-break:break-word">'+esc(r.linea)+'</div>'+
    '<div class="pv-grid"><label class="f"><span>Operador</span><select id="impOp_'+i+'">'+opts+'</select></label>'+
    '<label class="f"><span>Tractor</span><input type="text" id="impTr_'+i+'" value="'+esc(e.tractor)+'" placeholder="'+esc(inf.hab||'Sin tractor')+'" maxlength="20"></label>'+
    '<label class="f"><span>Nodriza</span><input type="text" id="impNo_'+i+'" list="nodList" value="'+esc(e.nodriza)+'" maxlength="8" style="text-transform:uppercase"></label></div>'+
    (e.crear?'<div class="grid2"><label class="f"><span>No. del operador nuevo (empleado, o el tractor si no lo tienes)</span><input type="text" id="impCn_'+i+'" value="'+esc(e.cNum)+'" maxlength="20" placeholder="Ej. 135001"></label><label class="f"><span>Nombre</span><input type="text" id="impCm_'+i+'" value="'+esc(e.cName)+'"></label></div>':'')+
    (avisos.length?'<div class="avisos">'+avisos.map(esc).join('<br>')+'</div>':'')+
    '<div class="row">'+(inf.cambio&&!e.skip?'<label class="chk"><input type="checkbox" id="impGu_'+i+'"'+(e.guardar?' checked':'')+'> Dejar '+esc(e.tractor)+' como su tractor habitual</label>':'')+
    '<label class="chk"><input type="checkbox" id="impSk_'+i+'"'+(e.skip?' checked':'')+'> No cargar este renglón</label></div></div>';
}
function importModal(){
  var m=U.importMsg,rows=U.imp.rows,sm=impSummary();
  var h='<div class="stack"><p class="sub" style="margin:0">Pega las líneas de Excel. Sirve con o sin <b>número de empleado</b>, con o sin <b>tractor</b> y con o sin <b>nombre</b>: el sistema identifica al operador por su número de empleado, por su tractor habitual o por su nombre, y tú revisas antes de cargar. Si ya cargaste a un operador ese día con esa nodriza, se omite.</p>'+
   '<label class="f"><span>Fecha de las asignaciones</span><input type="date" id="impDate" value="'+esc(U.f.impDate||S.date)+'"></label>'+
   '<textarea id="impText" placeholder="24063&#9;ARMANDO CARDENAS&#9;AB2402&#9;6 MAVERICKS&#10;134432&#9;AB2406&#9;6 MAVERICKS">'+esc(U.f.impText||'')+'</textarea>';
  if(U.imp.loading)h+='<div class="sub">Leyendo la lista…</div>';
  else if(U.imp.err)h+='<div class="note bad">'+esc(U.imp.err)+'</div>';
  else if(rows.length)h+='<div class="sub"><b>'+sm.n+'</b> listos'+(sm.pend?' · <b style="color:var(--bad)">'+sm.pend+' por completar</b>':'')+(sm.skip?' · '+sm.skip+' omitidos':'')+' · '+rows.length+' renglones leídos</div><div class="pv">'+rows.map(pvRow).join('')+'</div>';
  else if((U.f.impText||'').trim())h+='<div class="note warn">No se reconoció ningún renglón. Cada línea necesita al menos la nodriza (ej. AB2402) y la cantidad (ej. 6 MAVERICKS).</div>';
  if(m)h+='<div class="note '+((m.errores&&m.errores.length)?'warn':'ok')+'">Se cargaron <b>'+m.made+'</b> viajes'+(m.skipped?', '+m.skipped+' ya existían':'')+(m.creados?', se dieron de alta '+m.creados+' operadores nuevos':'')+(m.nods&&m.nods.length?', se registraron '+m.nods.length+' nodrizas nuevas con 0 bandas ('+m.nods.map(esc).join(', ')+')':'')+'.'+((m.errores&&m.errores.length)?'<br><b>No se pudieron cargar '+m.errores.length+':</b><ul class="list">'+m.errores.map(function(x){return '<li>'+esc(x)+'</li>'}).join('')+'</ul>':'')+'</div>';
  h+='<button class="btn primary big" data-act="doImport"'+((!sm.n||sm.pend||U.imp.loading)?' disabled':'')+'>Cargar '+(sm.n?sm.n+' ':'')+'asignaciones</button></div>';
  return h;
}
function notesOfTrip(t){
  var out=[];
  if(t.comentario)out.push({who:'Operador',label:'Comentario al firmar',text:t.comentario,ts:t.firmaTs});
  (t.requests||[]).forEach(function(r){if(r.note)out.push({who:'Operador',label:'Solicitó '+plural(r.n),text:r.note,ts:r.ts})});
  (t.entregas||[]).forEach(function(e){if(e.note)out.push({who:'Administración',label:'Entregó '+plural(e.n)+' ('+e.src+')',text:e.note,ts:e.ts})});
  (t.ajustes||[]).forEach(function(a){if(a.note)out.push({who:'Administración',label:'Ajustó el total a '+a.to,text:a.note,ts:a.ts})});
  return out.sort(function(a,b){return (a.ts||0)-(b.ts||0)});
}
function tripModal(t){
  var d=difOf(t),pend=pendingReqs(t),rec=(t.requests||[]).filter(function(r){return r.status!=='pendiente'});
  var h='<div class="kvs">'+kv('Asignación',chips(t))+kv('Tractor',esc(t.tractor||'—'))+kv('Requerido',t.required)+
    kv('Aceptó',t.acceptedTs?fmtTime(t.acceptedTs):'—')+kv('Sistema decía',t.sysInv==null?'—':(t.sysInv||'sin registro'))+kv('Conteo del operador',t.base==null?'—':t.base)+
    kv('Entregadas',sumK(t.entregas,'n'))+kv('Ajustes',(function(){var a=sumK(t.ajustes,'delta');return a>0?'+'+a:a})())+
    kv('Total',t.total==null?'—':t.total)+kv('Diferencia','<span class="dif '+difCls(d)+'" style="font-size:19px">'+difTxt(d)+'</span>')+'</div>';
  if(S.isAdmin)h+='<div class="sect" style="margin-top:0;border-top:none;padding-top:0"><div class="row" style="align-items:flex-end"><label class="f" style="flex:1;min-width:150px"><span>Cambiar tractor de este viaje</span><input type="text" id="trNew" value="'+esc(U.f.trNew!=null?U.f.trNew:(t.tractor||''))+'" maxlength="20"></label>'+
    '<label class="chk" style="padding-bottom:10px"><input type="checkbox" id="trHab"'+(U.f.trHab?' checked':'')+'> También como habitual</label><button class="btn" data-act="saveTractor" data-id="'+esc(t.id)+'">Guardar tractor</button></div></div>';
  if(t.verified&&t.invOk===false)h+='<div class="note warn">El operador contó distinto al sistema ('+(t.sysInv==null?'—':t.sysInv)+' → '+t.base+'). El inventario de '+esc(t.nodriza)+' ya se ajustó a lo que contó.'+(t.hasPhoto?' Foto de evidencia:':' No adjuntó foto.')+'</div>'+photoHtml(t);
  else if(t.hasPhoto)h+=photoHtml(t);
  var notes=notesOfTrip(t);
  if(notes.length)h+='<div class="sect"><h3>Comentarios</h3>'+notes.map(function(n){return '<div class="comment"><small>'+esc(n.who)+' · '+esc(n.label)+(n.ts?' · '+fmtTime(n.ts):'')+'</small>'+esc(n.text)+'</div>'}).join('')+'</div>';
  if(!t.verified)return h+'<div class="note info" style="margin-top:10px">'+(t.accepted?'El operador aceptó la asignación y aún no cuenta las bandas.':'El operador aún no acepta la asignación.')+(S.isAdmin?' Cuando cuente podrás entregar o ajustar desde aquí. Para corregir el total de la nodriza antes, usa Inventario.':'')+'</div>'+(S.isAdmin?delSection(t):'');
  if(pend.length){
    if(S.isAdmin)h+='<div class="sect"><h3>Solicitudes pendientes</h3>'+pend.map(function(r){return resolveForm(t,r)}).join('')+'</div>';
    else h+='<div class="sect"><h3>Solicitudes pendientes</h3>'+pend.map(function(r){return '<div class="note warn" style="margin-bottom:8px">Pide <b>'+r.n+'</b> bandas'+(r.note?' — '+esc(r.note):'')+' <small>('+fmtTime(r.ts)+')</small>. La aprueba el administrador.</div>'}).join('')+'</div>';
  }
  if(S.isAdmin){
    var stockHint='Almacén: '+(S.stock.almacen==null?'sin dato':S.stock.almacen)+' · Oficina: '+(S.stock.oficina==null?'sin dato':S.stock.oficina);
    h+='<div class="sect"><h3>Entregar bandas directo</h3><p class="sub" style="margin:0 0 8px">'+stockHint+'. Al entregar se descuenta del origen, se suma a la nodriza y se avisa al operador.</p><div class="grid2">'+
      '<label class="f"><span>Cantidad</span><input type="number" min="1" id="dvN" value="'+esc(U.f.dvN||'')+'"></label>'+
      '<label class="f"><span>Salieron de</span><select id="dvSrc">'+srcOpts(U.f.dvSrc||'almacen')+'</select></label>'+
      ((U.f.dvSrc||'almacen')==='nodriza'?'<label class="f"><span>Código de la nodriza</span><input type="text" id="dvCode" list="nodList" value="'+esc(U.f.dvCode||'')+'" placeholder="AT2301"></label>':'')+
      '<label class="f"><span>Nota'+((U.f.dvSrc==='otro')?' (dónde estaban)':' (opcional)')+'</span><input type="text" id="dvNote" value="'+esc(U.f.dvNote||'')+'"></label></div>'+
      '<button class="btn primary" style="margin-top:10px" data-act="doDeliver" data-id="'+esc(t.id)+'">Registrar entrega</button></div>';
    h+='<div class="sect"><h3>Corregir total</h3><div class="grid2"><label class="f"><span>Total correcto</span><input type="number" min="0" id="adN" value="'+esc(U.f.adN||'')+'" placeholder="'+t.total+'"></label>'+
      '<label class="f"><span>Motivo (opcional)</span><input type="text" id="adNote" value="'+esc(U.f.adNote||'')+'"></label></div><button class="btn" style="margin-top:10px" data-act="doAdjust" data-id="'+esc(t.id)+'">Guardar total</button></div>';
  }
  h+='<div class="sect"><h3>Firma del operador</h3>'+(t.firmado?'<div class="sigbox"><div><p class="sub" style="margin:0 0 6px">Firmó el '+fmtTime(t.firmaTs)+' con '+t.totalFirmado+' bandas.</p>'+sigHtml(t)+'</div></div>':'<p class="sub" style="margin:0">El operador aún no firma.</p>')+'</div>';
  if(rec.length)h+='<div class="sect"><h3>Solicitudes anteriores</h3><ul class="list">'+rec.map(function(r){return '<li>'+r.n+' bandas · '+esc(({atendida:'aprobada',rechazada:'rechazada',cancelada:'cancelada',pendiente:'pendiente'})[r.status]||r.status)+' <span class="sub">'+fmtTime(r.ts)+'</span></li>'}).join('')+'</ul></div>';
  h+='<div class="sect"><h3>Historial</h3><ul class="list">'+(t.log||[]).slice().reverse().map(function(l){return '<li><span class="sub">'+fmtTime(l.ts)+' · '+esc(l.who)+'</span><br>'+esc(l.text)+'</li>'}).join('')+'</ul></div>';
  return h+(S.isAdmin?delSection(t):'');
}
function delSection(t){return '<div class="sect"><button class="btn danger sm" data-act="delTrip" data-id="'+esc(t.id)+'">Eliminar este viaje</button></div>'}
function nodHistModal(code){
  if(S.nodHist==null)return '<p class="sub">Cargando historial…</p>';
  var x=S.nods[code];
  return '<div class="kvs">'+kv('Bandas ahora',x&&x.bandas!=null?x.bandas:'—')+kv('Última actualización',x&&x.updated?esc(ago(x.updated)):'—')+kv('Por',x?esc(x.by||'—'):'—')+'</div>'+movTable(S.nodHist,false);
}
function rankModal(m){
  var L=U.rankDet;
  if(!L||L.key!==m.por+'|'+m.val)return '<div class="loading">Cargando…</div>';
  if(!L.rows.length)return '<div class="empty" style="padding:22px 16px">Sin solicitudes en este periodo.</div>';
  var tp=sumK(L.rows,'cantidad'),tu=0,seen={};L.rows.forEach(function(r){if(!seen[r.tripId]){seen[r.tripId]=1;tu+=r.puestas}});
  var ST={pendiente:['Pendiente','warn'],atendida:['Atendida','ok'],rechazada:['Rechazada','bad']};
  return '<p class="sub" style="margin:0 0 10px">'+L.rows.length+' solicitudes · '+tp+' bandas pedidas · '+tu+' puestas en esos viajes. Del '+esc(fmtShort(L.desde))+' al '+esc(fmtShort(L.hasta))+'.</p>'+
   '<div class="tablewrap" style="margin-bottom:0"><table style="min-width:640px"><thead><tr><th>Fecha</th><th>Hora</th>'+(m.por==='op'?'<th>Nodriza</th>':'<th>Operador</th>')+'<th class="n">Pidió</th><th class="n">Puestas</th><th>Estado</th><th>Nota</th><th></th></tr></thead><tbody>'+
   L.rows.map(function(r){var e=ST[r.estado]||[esc(r.estado),'neutral'];
     return '<tr><td><b>'+esc(fmtDate(r.fecha))+'</b></td><td class="sub">'+(r.ts?esc(new Date(r.ts).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'})):'—')+'</td>'+
       (m.por==='op'?'<td><b>'+esc(r.nodriza)+'</b>'+(r.tractor?' <span class="sub">· tractor '+esc(r.tractor)+'</span>':''):'<td>'+esc(r.opName)+' <span class="sub">('+esc(r.opNum)+')</span>')+'</td>'+
       '<td class="n">'+r.cantidad+'</td><td class="n">'+r.puestas+'</td><td><span class="tag '+e[1]+'">'+e[0]+'</span></td><td class="sub">'+esc(r.nota||'')+'</td>'+
       '<td><button class="btn sm" data-act="openTripAt" data-id="'+esc(r.tripId)+'" data-f="'+esc(r.fecha)+'">Abrir viaje</button></td></tr>'}).join('')+'</tbody></table></div>';
}
function modalHtml(){
  var m=U.modal;if(!m)return '';var title,inner;
  if(m.type==='import'){title='Cargar asignación';inner=importModal()}
  else if(m.type==='myPwd'){title='Cambiar mi contraseña';inner=pwdForm(false)}
  else if(m.type==='pwdReset'){title='Restablecer contraseña · '+esc(m.nombre);inner='<div class="stack"><p class="sub" style="margin:0">Escribe una contraseña temporal. La persona deberá cambiarla en su próximo acceso.</p><label class="f"><span>Contraseña temporal (mínimo 6)</span><input type="text" id="rpPwd" value="'+esc(U.f.rpPwd||'')+'" autocomplete="off"></label><button class="btn primary big" data-act="savePwdReset" data-id="'+m.id+'">Guardar</button></div>'}
  else if(m.type==='rank'){title=esc(m.nm)+(m.por==='op'?' · solicitudes de bandas':' · solicitudes por nodriza');inner=rankModal(m)}
  else if(m.type==='nodHist'){title='Historial · '+esc(m.code);inner=nodHistModal(m.code)}
  else{var t=findTrip(m.id);if(!t){title='Viaje';inner='<p>Este viaje ya no existe.</p>'}else{title=esc(t.nodriza)+' · '+esc(t.opName)+' ('+esc(t.opNum)+')';inner=tripModal(t)}}
  return '<div class="overlay" data-act="bg"><div class="modal" role="dialog" aria-modal="true"><div class="mhead"><h2 style="font-size:20px">'+title+'</h2><button class="btn icon" data-act="closeModal" aria-label="Cerrar">×</button></div><div class="mbody" id="mbody">'+inner+'</div></div></div>';
}

/* ---------- render ---------- */
function view(){
  var toastH=(U.toast?'<div class="toast" role="status">'+esc(U.toast)+'</div>':'');
  if(!S.ready)return '<div class="loading">Cargando…</div>';
  if(!S.opNum)return header(false)+loginView()+toastH;
  if(U.mustChange)return header(false)+pwdForcedView()+toastH;
  var admin=S.isStaff&&!U.viewAs;
  return header(admin)+(admin?adminView():operatorView())+modalHtml()+toastH;
}
var rTimer=null;
function requestRender(){if(rTimer)return;rTimer=setTimeout(function(){rTimer=null;render()},60)}
function render(){
  var ae=document.activeElement;
  var fo=(ae&&ae.id&&/INPUT|TEXTAREA|SELECT/.test(ae.tagName)&&ae.closest&&ae.closest('#app'))?{id:ae.id,s:ae.selectionStart,e:ae.selectionEnd}:null;
  var mb=$('#mbody');var mt=mb?mb.scrollTop:0;
  var sy=window.scrollY;
  $('#app').innerHTML=view();
  var nb=$('#mbody');if(nb)nb.scrollTop=mt;
  if(fo){var el=document.getElementById(fo.id);if(el){el.focus();try{if(fo.s!=null&&el.type!=='number'&&el.type!=='date')el.setSelectionRange(fo.s,fo.e)}catch(x){}}}
  var dl=$('#nodList');if(dl)dl.innerHTML=Object.keys(S.nods).sort().map(function(c){return '<option value="'+esc(c)+'">'}).join('');
  if(Math.abs(window.scrollY-sy)>2)window.scrollTo(0,sy);
}
var toastT=null;
function toast(m){U.toast=m;render();clearTimeout(toastT);toastT=setTimeout(function(){U.toast='';render()},3600)}
async function run(fn){
  if(U.busy)return;U.busy=true;
  try{await fn()}catch(e){toast((e&&e.message)||'Ocurrió un error')}
  U.busy=false;render();
}

/* ---------- eventos ---------- */
var FORM_RERENDER={fText:1,nodQ:1,dvSrc:1,trO:1,trD:1,uQ:1};
document.addEventListener('input',function(e){
  var el=e.target;if(!el.id)return;
  if(el.type==='checkbox'){if(!/^imp/.test(el.id))U.f[el.id]=el.checked;return}
  if(el.id==='impText'){U.f.impText=el.value;schedulePreview();return}
  var im=/^imp(Tr|No|Cn|Cm)_(\d+)$/.exec(el.id);
  if(im){var rw=U.imp.rows[+im[2]];if(rw){var kk={Tr:'tractor',No:'nodriza',Cn:'cNum',Cm:'cName'}[im[1]];rw.ed[kk]=el.value;render()}return}
  if(el.dataset&&(el.dataset.nod||el.dataset.userTractor||el.dataset.userRol))return;
  U.f[el.id]=el.value;
  if(el.id==='vN'&&U.verify){var d=$('#vDyn');if(d)d.innerHTML=verifyDyn();return}
  if(FORM_RERENDER[el.id]||/^apSrc_/.test(el.id))render();
});
document.addEventListener('change',function(e){
  var el=e.target;
  if(el.id==='photoInp'){
    var f=el.files&&el.files[0];el.value='';if(!f)return;
    compressImage(f,1280,0.72).then(function(url){U.photo=url;render()}).catch(fail);return;
  }
  if(el.id==='dateInp'&&el.value){setDate(el.value);return}
  if(el.id==='invSort'){U.invSort=el.value;render();return}
  var ic=/^imp(Op|Sk|Gu)_(\d+)$/.exec(el.id);
  if(ic){var rw=U.imp.rows[+ic[2]];if(!rw)return;
    if(ic[1]==='Op'){if(el.value==='__new'){rw.ed.crear=true;rw.ed.opNum=''}else{rw.ed.crear=false;rw.ed.opNum=el.value}}
    else if(ic[1]==='Sk')rw.ed.skip=el.checked;else rw.ed.guardar=el.checked;
    render();return}
  if(el.dataset&&el.dataset.userTractor){var uid=+el.dataset.userTractor;run(async function(){await post('/api_usuarios.php',{accion:'actualizar',id:uid,tractor:el.value.trim()});await cargarUsuarios(true);toast('Tractor guardado')});return}
  if(el.dataset&&el.dataset.userRol){var uid2=+el.dataset.userRol;run(async function(){await post('/api_usuarios.php',{accion:'actualizar',id:uid2,rol:el.value});await cargarUsuarios(true);toast('Rol actualizado')});return}
  if(el.dataset&&el.dataset.nod){
    var n=parseInt(el.value,10);if(isNaN(n)||n<0){toast('Escribe un número válido');return}
    run(async function(){await setNodriza(el.dataset.nod,n,'Ajuste manual en la tabla de inventario');toast('Guardado '+el.dataset.nod)});
  }
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'&&U.modal&&!SIG.open){U.modal=null;render()}
  if(e.key==='Enter'&&(e.target.id==='lgNum'||e.target.id==='lgPwd')){var b=document.querySelector('[data-act="login"]');if(b)b.click()}
  if(e.key==='Enter'&&/^pw(A|N|N2)$/.test(e.target.id)){var b2=document.querySelector('[data-act="savePwd"],[data-act="savePwdForced"]');if(b2)b2.click()}
});
document.addEventListener('click',function(e){
  var b=e.target.closest('[data-act]');if(!b)return;
  var a=b.getAttribute('data-act'),id=b.getAttribute('data-id');
  if(a==='bg'){if(e.target===b){U.modal=null;render()}return}
  var acts={
    tab:function(){U.tab=id;render();
      if(id==='inv'||id==='rep')cargarInventario(true);if(id==='rep'&&!S.rep)run(loadReport);else if(id==='sol')cargarPendientes(true);else if(id==='asig')cargarViajes(true);
      else if(id==='panel')cargarStats();
      else if(id==='cal'){cargarCal();cargarViajes(true);cargarNotasDia(true)}
      else if(id==='usrs')cargarUsuarios()},
    statsPreset:function(){U.statsPreset=id;if(id!=='custom'){S.stats=null;cargarStats()}render()},
    statsApply:function(){
      var a=U.f.stDesde,b=U.f.stHasta;if(!a||!b){var r=statsRange();a=a||r.desde;b=b||r.hasta;U.f.stDesde=a;U.f.stHasta=b}
      if(b<a){toast('La fecha final debe ser igual o posterior a la inicial');return}
      S.stats=null;render();cargarStats()},
    invView:function(){U.invView=id;render()},
    rankDet:function(){
      var por=b.getAttribute('data-por'),nm=b.getAttribute('data-nm'),r=statsRange();
      U.modal={type:'rank',por:por,val:id,nm:nm};render();
      get('/api_estadisticas.php?tipo=detalle&por='+por+'&valor='+encodeURIComponent(id)+'&desde='+r.desde+'&hasta='+r.hasta).then(function(d){
        if(!d.success)throw new Error(d.error||d.message||'No se pudo cargar');
        U.rankDet={key:por+'|'+id,rows:d.solicitudes||[],desde:r.desde,hasta:r.hasta};render()}).catch(fail)},
    openTripAt:function(){var f=b.getAttribute('data-f');
      get('/api_viajes.php?fecha='+encodeURIComponent(f)).then(function(d){
        if(!d.success)throw new Error(d.error||d.message||'No se pudo abrir');
        (d.data||[]).forEach(function(x){S.cache[x.id]=norm(x)});
        if(!S.cache[id]){toast('Ese viaje ya no existe');return}
        U.f.dvN='';U.f.dvSrc='almacen';U.f.dvCode='';U.f.dvNote='';U.f.adN='';U.f.adNote='';U.f.trNew=null;U.f.trHab=false;
        U.modal={type:'trip',id:id};render()}).catch(fail)},
    panelDay:function(){U.tab='cal';U.calMes=id.slice(0,7);S.date=id;S.trips=[];S.dayNotes=[];S.cal=null;render();cargarCal();cargarViajes();cargarNotasDia()},
    toggleDias:function(){U.f.diasAll=!U.f.diasAll;render()},
    toggleOpAll:function(){U.f.opAll=!U.f.opAll;render()},
    toggleOpTable:function(){U.f.opTable=!U.f.opTable;render()},
    exportStatsCsv:exportStatsCsv,
    printPage:function(){window.print()},
    calPrev:function(){var y=+calMes().slice(0,4),m=+calMes().slice(5,7)-1;if(m<1){m=12;y--}U.calMes=y+'-'+pad(m);S.cal=null;render();cargarCal()},
    calNext:function(){var y=+calMes().slice(0,4),m=+calMes().slice(5,7)+1;if(m>12){m=1;y++}U.calMes=y+'-'+pad(m);S.cal=null;render();cargarCal()},
    calToday:function(){U.calMes=todayStr().slice(0,7);S.cal=null;S.date=todayStr();S.trips=[];S.dayNotes=[];render();cargarCal();cargarViajes();cargarNotasDia()},
    calDay:function(){S.date=id;S.trips=[];S.dayNotes=[];render();cargarViajes();cargarNotasDia()},
    goAsig:function(){U.tab='asig';render();cargarViajes(true)},
    myPwd:function(){U.f.pwA=U.f.pwN=U.f.pwN2='';U.modal={type:'myPwd'};render()},
    savePwd:function(){run(function(){return savePassword(false)})},
    savePwdForced:function(){run(function(){return savePassword(true)})},
    pwdReset:function(){var u=(S.users||[]).filter(function(x){return String(x.id)===id})[0];if(!u)return;U.f.rpPwd='';U.modal={type:'pwdReset',id:u.id,nombre:u.nombre};render()},
    savePwdReset:function(){
      var pw=(U.f.rpPwd||'').trim();if(pw.length<6){toast('Mínimo 6 caracteres');return}
      run(async function(){await post('/api_usuarios.php',{accion:'reset_password',id:+id,password:pw});U.modal=null;await cargarUsuarios(true);toast('Contraseña restablecida. Deberá cambiarla al entrar.')});
    },
    toggleUser:function(){
      var u=(S.users||[]).filter(function(x){return String(x.id)===id})[0];if(!u)return;
      var nuevo=u.estado==='activo'?'inactivo':'activo';
      if(nuevo==='inactivo'&&!confirm('¿Desactivar a '+u.nombre+'? Ya no podrá entrar al sistema.'))return;
      run(async function(){await post('/api_usuarios.php',{accion:'actualizar',id:+id,estado:nuevo});await cargarUsuarios(true);toast(nuevo==='activo'?'Usuario activado':'Usuario desactivado')});
    },
    askAllPwd:function(){
      if(!confirm('Todos los operadores tendrán que crear una contraseña nueva la próxima vez que entren. ¿Continuar?'))return;
      run(async function(){var d=await post('/api_usuarios.php',{accion:'pedir_cambio_todos'});await cargarUsuarios(true);toast('Listo: '+d.afectados+' operadores deberán cambiar su contraseña')});
    },
    saveTractor:function(){
      var t=(U.f.trNew!=null?U.f.trNew:'').trim(),hab=!!U.f.trHab;
      run(async function(){await act('cambiar_tractor',id,{tractor:t,habitual:hab});U.f.trNew=null;U.f.trHab=false;await cargarUsuarios(true);toast('Tractor actualizado')});
    },
    filter:function(){U.filter=id;render()},
    prevDay:function(){setDate(shiftDate(S.date,-1))},
    nextDay:function(){setDate(shiftDate(S.date,1))},
    today:function(){setDate(todayStr())},
    viewAs:function(){U.viewAs=!U.viewAs;U.modal=null;render()},
    closeModal:function(){U.modal=null;render()},
    openImport:function(){U.modal={type:'import'};U.importMsg=null;U.f.impDate=S.date;U.f.impText='';U.imp={rows:[],loading:false,err:''};render();cargarUsuarios(true)},
    doImport:function(){run(doImport)},
    openTrip:function(){var t=findTrip(id);var p=t?pendingReqs(t)[0]:null;
      U.f.dvN=p?String(p.n):'';U.f.dvSrc='almacen';U.f.dvCode='';U.f.dvNote='';U.f.adN='';U.f.adNote='';U.f.trNew=null;U.f.trHab=false;
      U.modal={type:'trip',id:id};render()},
    approve:function(){var rid=b.getAttribute('data-rid');run(function(){return approveReq(id,rid)})},
    reject:function(){var rid=b.getAttribute('data-rid');run(function(){return rejectReq(id,rid)})},
    doDeliver:function(){run(function(){return doDeliver(id)})},
    doAdjust:function(){run(function(){return doAdjust(id)})},
    delTrip:function(){if(confirm('¿Eliminar este viaje? No revierte el inventario.'))run(function(){return delTrip(id)})},
    exportCsv:exportCsv,exportInvCsv:exportInvCsv,exportMovCsv:exportMovCsv,

    saveStockMov:function(){
      var n=parseInt(U.f.smN,10);if(isNaN(n)||n<0){toast('Escribe una cantidad válida');return}
      run(async function(){
        await invPost({accion:'stock_movimiento',ubicacion:U.f.smUb||'almacen',modo:U.f.smModo||'entrada',cantidad:n,motivo:(U.f.smNota||'').trim()});
        U.f.smN='';U.f.smNota='';await cargarInventario(true);toast('Movimiento registrado');
      });
    },
    doTransfer:function(){
      var n=parseInt(U.f.trN,10);if(!(n>0)){toast('Escribe cuántas bandas mover');return}
      var o=U.f.trO||'almacen',d=U.f.trD||'nodriza';
      run(async function(){
        await invPost({accion:'transferir',origen:o,destino:d,origenCodigo:(U.f.trOC||'').trim(),destinoCodigo:(U.f.trDC||'').trim(),n:n,nota:(U.f.trNota||'').trim()});
        U.f.trN='';U.f.trNota='';await cargarInventario(true);toast('Bandas movidas');
      });
    },
    addNod:function(){
      var c=(U.f.anCod||'').trim().toUpperCase(),n=parseInt(U.f.anN,10);
      if(!/^[A-Z]{1,3}\d{3,5}$/.test(c)){toast('Código no válido. Ej. AC2410');return}
      if(isNaN(n)||n<0){toast('Escribe cuántas bandas trae');return}
      run(async function(){await invPost({accion:'crear_nodriza',codigo:c,bandas:n});U.f.anCod='';U.f.anN='';await cargarInventario(true);toast('Nodriza '+c+' agregada')});
    },
    delNod:function(){
      if(!confirm('¿Eliminar la nodriza '+id+' del inventario?'))return;
      run(async function(){await invPost({accion:'eliminar_nodriza',codigo:id});await cargarInventario(true);toast('Nodriza eliminada')});
    },
    nodHist:function(){
      U.modal={type:'nodHist',code:id};S.nodHist=null;render();
      get('/api_reportes.php?desde=0&hasta='+(Date.now()+86400000)+'&codigo='+encodeURIComponent(id)).then(function(d){S.nodHist=(d.movimientos||[]);render()}).catch(function(er){S.nodHist=[];fail(er)});
    },
    loadRep:function(){run(loadReport)},
    savePer:function(){
      var n=parseInt(U.f.cfgPer,10);if(!(n>0)){toast('Escribe un número mayor a 0');return}
      run(async function(){await invPost({accion:'actualizar_config',perUnit:n});U.f.cfgPer=null;await cargarInventario(true);toast('Configuración guardada')});
    },
    doInvImport:function(){run(doInvImport)},
    clearNotifs:function(){run(clearReadNotifs)},
    delDay:function(){run(delDay)},

    saveUser:function(){
      var num=(U.f.nuNum||'').trim(),nom=(U.f.nuNom||'').trim(),pwd=(U.f.nuPwd||'').trim(),rol=U.f.nuRol||'operador',tr=(U.f.nuTr||'').trim();
      if(!num||!nom||!pwd){toast('Llena número, nombre y contraseña');return}
      run(async function(){await post('/api_usuarios.php',{accion:'crear',num_empleado:num,nombre:nom,password:pwd,rol:rol,tractor:tr});U.f.nuNum='';U.f.nuNom='';U.f.nuPwd='';U.f.nuTr='';await cargarUsuarios(true);toast('Usuario creado')});
    },
    saveBulkUsers:function(){
      var txt=(U.f.nuBulk||'').trim();if(!txt){toast('Agrega al menos un operador');return}
      run(async function(){var res=await post('/api_usuarios.php',{accion:'masivo',datos:txt});toast('Se crearon '+res.creados+' operadores ('+res.omitidos+' omitidos)');U.f.nuBulk=(res.omitidosDetalle||[]).join('\n');await cargarUsuarios(true)});
    },

    login:function(){
      var num=(U.f.lgNum||'').trim(),pwd=(U.f.lgPwd||'');
      if(!num){U.loginErr='El número de empleado es requerido.';render();return}
      if(!pwd){U.loginErr='La contraseña es requerida.';render();return}
      U.loginErr='Conectando...';render();
      fetch('/login.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify({num_empleado:num,password:pwd})})
      .then(function(r){return r.json()})
      .then(function(data){
        if(data.success){U.loginErr='';U.f.lgPwd='';try{localStorage.setItem('bandas_op',num)}catch(x){}applySession(data.data);render()}
        else{U.loginErr=data.message||'Credenciales incorrectas';render()}
      }).catch(function(){U.loginErr='Error de conexión con el servidor.';render()});
    },
    logout:function(){
      U.loginErr='Cerrando sesión...';render();
      fetch('/logout.php',{credentials:'same-origin'}).then(function(){clearSession();U.loginErr='';render()}).catch(function(){clearSession();render()});
    },

    readNotif:function(){run(async function(){await post('/api_notificaciones.php',{accion:'leer',id:id});await cargarNotifs(true)})},
    accept:function(){run(function(){return doAccept(id)})},
    startVerify:function(){startVerify(id)},
    verifyYes:function(){var v=U.verify;run(function(){return sendCount(v.sys)})},
    verifyNo:function(){U.verify.mode='count';render()},
    verifySave:function(){
      var n=parseInt(U.f.vN,10);if(isNaN(n)||n<0){toast('Escribe cuántas bandas trae');return}
      if(U.verify.sys>0&&n<U.verify.sys&&!U.photo){toast('Toma la foto de la nodriza para continuar');return}
      run(function(){return sendCount(n)});
    },
    verifyCancel:function(){U.verify=null;U.photo=null;render()},
    pickPhoto:function(){var i=$('#photoInp');if(i)i.click()},
    openReq:function(){var t=findTrip(id);var d=difOf(t);U.reqOpen=id;U.f.rqN=String(d<0?-d:1);U.f.rqNote='';render()},
    cancelReqForm:function(){U.reqOpen=null;render()},
    sendReq:function(){
      var n=parseInt(U.f.rqN,10);if(!(n>0)){toast('Escribe cuántas bandas necesitas');return}
      run(async function(){await sendRequest(id,n,(U.f.rqNote||'').trim());U.reqOpen=null;toast('Solicitud enviada al administrador')});
    },
    cancelReq:function(){run(async function(){await act('cancelar_solicitud',id);toast('Solicitud cancelada')})},
    startSign:function(){var t=findTrip(id);if(!t||!t.verified||t.hasPending)return;openSign(id)}
  };
  if(acts[a])acts[a]();
});

/* ---------- arranque: intenta restaurar la sesión antes de mostrar el login ---------- */
async function init(){
  render();
  try{var sv=localStorage.getItem('bandas_op');if(sv)U.f.lgNum=sv}catch(e){}
  try{
    var res=await fetch('/check_session.php',{credentials:'same-origin'}).then(function(r){return r.json()});
    if(res&&res.success&&res.data)applySession(res.data);
  }catch(e){}
  S.ready=true;render();
}
init();
})();
</script>
</body>
</html>