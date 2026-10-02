{{-- Profile tab "Flugkarte": visited vs. still-open airports of the GSG network. Expects $user. --}}
@php($ufmKey = trim((string) (setting('acars.carto_api_key', env('CARTO_API_KEY', '')) ?? '')))
<style>
  .fmp-card { border:0; border-radius:16px; overflow:hidden; background:#0b0f17; box-shadow:0 10px 40px rgba(0,0,0,.35); }
  .fmp-head { background:linear-gradient(180deg,#121826,#0b0f17); border-bottom:1px solid rgba(255,255,255,.06); padding:12px 16px; }
  .fmp-stats { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .fmp-stat { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:7px 14px; color:#94a3b8; font-size:12px; font-weight:600; }
  .fmp-stat b { display:block; color:#fff; font-size:18px; font-weight:800; line-height:1.15; }
  .fmp-bar { height:8px; border-radius:99px; background:rgba(255,255,255,.08); overflow:hidden; margin-bottom:10px; }
  .fmp-bar > i { display:block; height:100%; background:linear-gradient(90deg,#34d399,#22d3ee); border-radius:99px; }
  .fmp-tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .fmp-pill { border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.04); color:#cbd5e1; border-radius:999px; padding:6px 14px; font-weight:600; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
  .fmp-pill.on { background:linear-gradient(135deg,#22d3ee,#6366f1); color:#fff; border-color:transparent; }
  .fmp-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
  .fmp-legend { margin-left:auto; color:#94a3b8; font-size:12px; display:flex; gap:14px; align-items:center; }
  #fmp-map { background:#0b0f17; width:100%; height:560px; }
  #fmp-map .leaflet-tooltip.fm-tt { background:#111826; color:#e2e8f0; border:1px solid rgba(255,255,255,.12); border-radius:7px; font-weight:600; }
  #fmp-map .leaflet-tooltip.fm-tt::before { display:none; }
  #fmp-map .leaflet-popup-content-wrapper { background:#111826; color:#e2e8f0; border-radius:12px; }
  #fmp-map .leaflet-popup-tip { background:#111826; }
  .fmp-body { display:flex; }
  .fmp-body #fmp-map { flex:1 1 auto; min-width:0; }
  .fmp-side { display:none; flex:0 0 320px; height:560px; overflow:auto; background:#0e1420; border-left:1px solid rgba(255,255,255,.06); }
  .fmp-flown .fmp-side { display:block; }
  .fmp-row { display:grid; grid-template-columns:1fr auto; gap:2px 8px; padding:8px 12px; border-bottom:1px solid rgba(255,255,255,.05); cursor:pointer; color:#cbd5e1; font-size:12px; }
  .fmp-row:hover, .fmp-row.sel { background:rgba(34,211,238,.1); }
  .fmp-row b { color:#fff; font-size:13px; }
  .fmp-row .m { color:#7f8ea3; }
  .fmp-row .r { text-align:right; color:#94a3b8; }
  .fmp-sel { background:rgba(255,255,255,.05); color:#e2e8f0; border:1px solid rgba(255,255,255,.15); border-radius:999px; padding:5px 12px; font-size:13px; }
  .fmp-sel option { background:#111826; }
  .fmp-hint { padding:8px 16px; color:#94a3b8; font-size:12px; line-height:1.4; border-bottom:1px solid rgba(255,255,255,.05); background:#0e1420; }
  .fmp-help { padding:10px 16px; background:#0e1420; border-bottom:1px solid rgba(255,255,255,.05); color:#cbd5e1; font-size:12.5px; line-height:1.45; }
  .fmp-help h6 { color:#fff; font-size:13px; margin:0 0 6px; }
  .fmp-help .r { display:grid; grid-template-columns:34px 1fr; gap:8px; align-items:start; padding:4px 0; }
  .fmp-help .sw { display:flex; justify-content:center; padding-top:4px; }
  .fmp-help .sw i { display:block; }
  .fmp-help .dot { width:12px; height:12px; border-radius:50%; }
  .fmp-help .ln { width:28px; height:0; border-top:3px solid #22d3ee; margin-top:5px; }
  .fmp-help .ln.dash { border-top:2px dashed #22d3ee; }
  .fmp-help .tx { color:#94a3b8; font-size:12px; }
  .fmp-hide { display:none !important; }
  @media (max-width:900px) { .fmp-body { flex-direction:column; } .fmp-side { flex-basis:auto; height:260px; border-left:0; border-top:1px solid rgba(255,255,255,.06); } #fmp-map { height:420px; } }
</style>
<div class="fmp-card" id="fmp-pcard">
  <div class="fmp-head">
    <div class="fmp-tools mb-2">
      <button type="button" class="fmp-pill on" id="fmp-m-visited"><i class="ph-fill ph-map-pin"></i>@lang('userflightmap::messages.m_visited')</button>
      <button type="button" class="fmp-pill" id="fmp-m-flown"><i class="ph-fill ph-airplane-in-flight"></i>@lang('userflightmap::messages.m_flown')</button>
      <button type="button" class="fmp-pill" id="fmp-help-btn" style="margin-left:auto" aria-expanded="false"><i class="ph-fill ph-question"></i>@lang('userflightmap::messages.help')</button>
    </div>
    <div class="fmp-stats" id="fmp-stats"></div>
    <div class="fmp-tools" id="fmp-tools-visited">
      <button type="button" class="fmp-pill on" id="fmp-t-lines"><i class="ph-fill ph-line-segments"></i>@lang('userflightmap::messages.p_routes')</button>
      <button type="button" class="fmp-pill on" id="fmp-t-open"><i class="ph-fill ph-circle-dashed"></i>@lang('userflightmap::messages.p_open')</button>
      <span class="fmp-legend">
        <span><span class="fmp-dot" style="background:#34d399"></span> @lang('userflightmap::messages.p_visited')</span>
        <span><span class="fmp-dot" style="background:#64748b"></span> @lang('userflightmap::messages.p_legend_open')</span>
      </span>
    </div>
  </div>
  <div class="fmp-hint" id="fmp-hint"></div>
  <div class="fmp-help fmp-hide" id="fmp-help"></div>
  <div class="fmp-body">
    <div id="fmp-map"></div>
    <div class="fmp-side" id="fmp-side"></div>
  </div>
</div>
<script>
(function () {
  var started = false;
  var T = {
    visitedAirports: @json(__('userflightmap::messages.p_visited_airports')),
    ofNetwork:       @json(__('userflightmap::messages.p_of_network')),
    countries:       @json(__('userflightmap::messages.p_countries')),
    routes:          @json(__('userflightmap::messages.routes')),
    flights:         @json(__('userflightmap::messages.p_flights')),
    notYet:          @json(__('userflightmap::messages.p_not_yet')),
    visited:         @json(__('userflightmap::messages.p_visited')),
    fFlights: @json(__('userflightmap::messages.f_flights')),
    fDistance: @json(__('userflightmap::messages.f_distance')),
    fHours: @json(__('userflightmap::messages.f_hours')),
    fTracked: @json(__('userflightmap::messages.f_tracked')),
    fAllYears: @json(__('userflightmap::messages.f_all_years')),
    fNoTrack: @json(__('userflightmap::messages.f_no_track')),
    fLoading: @json(__('userflightmap::messages.f_loading')),
    flownOnRoute: @json(__('userflightmap::messages.p_flown_on_route')),
    hintVisited: @json(__('userflightmap::messages.hint_visited')),
    hintFlown: @json(__('userflightmap::messages.hint_flown')),
    mVisited: @json(__('userflightmap::messages.m_visited')),
    mFlown: @json(__('userflightmap::messages.m_flown')),
    helpVisited: @json(trans('userflightmap::messages.help_visited')),
    helpFlown: @json(trans('userflightmap::messages.help_flown')),
    helpTitleVisited: @json(__('userflightmap::messages.help_title_visited')),
    helpTitleFlown: @json(__('userflightmap::messages.help_title_flown'))
  };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }

  function start() {
    if (started) { return; }
    started = true;
    var map = L.map('fmp-map', { worldCopyJump: true, minZoom: 2, scrollWheelZoom: true, renderer: L.canvas({ tolerance: 14 }) });
    L.tileLayer(@json($ufmKey !== '' ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key='.$ufmKey : 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}'), { attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 16 }).addTo(map);
    map.setView([30, 10], 2);
    var visitedHtml = '', mode = 'visited';
    var gLines = L.featureGroup().addTo(map), gOpen = L.featureGroup().addTo(map), gVisited = L.featureGroup().addTo(map);

    phpvms.request({ url: '{{ url('/userflightmap/profile/'.$user->id) }}' }).then(function (r) {
      var d = r.data, s = d.stats;
      var pct = s.network ? Math.round(100 * s.network_visited / s.network) : 0;
      visitedHtml =
        '<div class="fmp-stat"><b>' + s.visited + '</b>' + T.visitedAirports + '</div>' +
        '<div class="fmp-stat"><b>' + s.network_visited + ' / ' + s.network + ' (' + pct + '%)</b>' + T.ofNetwork + '</div>' +
        '<div class="fmp-stat"><b>' + s.countries + '</b>' + T.countries + '</div>';
      if (mode === 'visited') { document.getElementById('fmp-stats').innerHTML = visitedHtml; }

      var maxC = 1; d.lines.forEach(function (l) { if (l[6] > maxC) { maxC = l[6]; } });
      d.lines.forEach(function (l) {
        var g = new (L.Geodesic || L.Polyline)([], { color: '#22d3ee', weight: 1 + Math.round(3 * Math.sqrt(l[6]) / Math.sqrt(maxC)), opacity: 0.4, wrap: false });
        g.setLatLngs([[l[0], l[1]], [l[2], l[3]]]);
        g.bindPopup('<b>' + esc(l[4]) + ' → ' + esc(l[5]) + '</b><br>' + l[6] + '× ' + T.flownOnRoute);
        g.addTo(gLines);
      });
      d.open.forEach(function (a) {
        var m = L.circleMarker([a[2], a[3]], { radius: 3.5, color: '#0b0f17', weight: 0.5, fillColor: '#64748b', fillOpacity: 0.8, bubblingMouseEvents: false });
        m.bindPopup('<b>' + esc(a[0]) + '</b><br>' + esc(a[1]) + '<br><span style="color:#94a3b8">' + T.notYet + '</span>');
        m.addTo(gOpen);
      });
      d.visited.forEach(function (a) {
        var m = L.circleMarker([a[2], a[3]], { radius: 5 + Math.min(4, Math.log2(a[4] + 1)), color: '#ecfdf5', weight: 1.2, fillColor: '#34d399', fillOpacity: 1, bubblingMouseEvents: false });
        m.bindPopup('<b>' + esc(a[0]) + '</b><br>' + esc(a[1]) + '<br><span style="color:#34d399">' + a[4] + ' ' + T.flights + '</span>');
        m.addTo(gVisited);
      });
      try { var b = gVisited.getBounds(); if (b.isValid()) { map.fitBounds(b, { padding: [40, 40], maxZoom: 6 }); } } catch (e) {}
    });

    function toggle(id, group) {
      document.getElementById(id).addEventListener('click', function () {
        var on = this.classList.toggle('on');
        if (on) { group.addTo(map); gVisited.bringToFront(); } else { map.removeLayer(group); }
      });
    }
    toggle('fmp-t-lines', gLines);
    toggle('fmp-t-open', gOpen);

    // ---- Volanta-style history: flown flights with real ACARS tracks ----
    var gFlown = L.featureGroup(), flights = null, flLayers = {}, selId = null, year = '';
    var COLORS = ['#22d3ee', '#a78bfa', '#f472b6', '#fbbf24', '#34d399', '#fb7185', '#60a5fa'];
    function unwrap(pts) {
      var out = [], off = 0, prev = null;
      pts.forEach(function (p) {
        if (prev !== null) { var dl = p[1] - prev; if (dl > 180) { off -= 360; } else if (dl < -180) { off += 360; } }
        prev = p[1]; out.push([p[0], p[1] + off]);
      });
      return out;
    }
    function fmtDate(d) { var x = String(d).split('-'); return x.length === 3 ? x[2] + '.' + x[1] + '.' + x[0] : d; }
    function fmtDur(m) { return Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2); }
    function yearColor(y, years) { return COLORS[years.indexOf(y) % COLORS.length]; }

    function renderFlown() {
      gFlown.clearLayers(); flLayers = {}; selId = null;
      var years = []; flights.forEach(function (f) { var y = String(f.date).slice(0, 4); if (years.indexOf(y) < 0) { years.push(y); } });
      var list = flights.filter(function (f) { return !year || String(f.date).slice(0, 4) === year; });
      var nm = 0, min = 0, tracked = 0;
      list.forEach(function (f) { nm += f.nm || 0; min += f.min || 0; if (f.track) { tracked++; } });
      var sel = '<select class="fmp-sel" id="fmp-year"><option value="">' + T.fAllYears + '</option>' +
        years.map(function (y) { return '<option value="' + y + '"' + (y === year ? ' selected' : '') + '>' + y + '</option>'; }).join('') + '</select>';
      document.getElementById('fmp-stats').innerHTML =
        '<div class="fmp-stat"><b>' + list.length + '</b>' + T.fFlights + '</div>' +
        '<div class="fmp-stat"><b>' + nm.toLocaleString('de-DE') + ' nm</b>' + T.fDistance + '</div>' +
        '<div class="fmp-stat"><b>' + Math.round(min / 60).toLocaleString('de-DE') + ' h</b>' + T.fHours + '</div>' +
        '<div class="fmp-stat" style="display:flex;align-items:center">' + sel + '</div>';
      document.getElementById('fmp-year').addEventListener('change', function () { year = this.value; renderFlown(); });

      var side = document.getElementById('fmp-side'), html = '';
      list.forEach(function (f) {
        var col = yearColor(String(f.date).slice(0, 4), years), parts = [];
        if (f.track) {
          var pts = unwrap(f.track);
          parts.push(L.polyline(pts, { color: col, weight: 6, opacity: 0.12, interactive: true }));
          parts.push(L.polyline(pts, { color: col, weight: 1.6, opacity: 0.85, interactive: false }));
        } else {
          var g = new (L.Geodesic || L.Polyline)([], { color: col, weight: 1, opacity: 0.3, dashArray: '4 5', wrap: false, interactive: true });
          g.setLatLngs([f.from, f.to]); parts.push(g);
        }
        var grp = L.featureGroup(parts).addTo(gFlown);
        grp.on('click', (function (ff) { return function (e) { L.DomEvent.stopPropagation(e); selectFlight(ff.id, false); openFlightPopup(ff, e.latlng); }; })(f));
        flLayers[f.id] = { grp: grp, col: col, f: f };
        L.circleMarker(f.from, { radius: 2.5, color: col, weight: 1, fillColor: col, fillOpacity: 1, interactive: false }).addTo(gFlown);
        L.circleMarker(f.to, { radius: 2.5, color: col, weight: 1, fillColor: '#0b0f17', fillOpacity: 1, interactive: false }).addTo(gFlown);
        html += '<div class="fmp-row" data-id="' + esc(f.id) + '"><b>' + esc(f.dpt) + ' → ' + esc(f.arr) + '</b><span class="r">' + fmtDate(f.date) + '</span>' +
          '<span class="m">' + esc(f.type || '') + (f.reg ? ' · ' + esc(f.reg) : '') + ' · ' + (f.nm || 0) + ' nm · ' + fmtDur(f.min || 0) + (f.track ? '' : ' · ' + T.fNoTrack) + '</span>' +
          '<span class="r">' + (f.lr != null ? f.lr + ' fpm' : '') + '</span></div>';
      });
      side.innerHTML = html;
      Array.prototype.forEach.call(side.querySelectorAll('.fmp-row'), function (el) {
        el.addEventListener('click', function () { selectFlight(el.getAttribute('data-id'), true); });
      });
      try { var b = gFlown.getBounds(); if (b.isValid()) { map.fitBounds(b, { padding: [30, 30], maxZoom: 7 }); } } catch (e) {}
    }

    function openFlightPopup(f, latlng) {
      var h = '<b>' + esc(f.dpt) + ' → ' + esc(f.arr) + '</b><br>' + esc(f.dname || '') + ' → ' + esc(f.aname || '') + '<br>' +
        fmtDate(f.date) + ' · ' + esc(f.type || '') + (f.reg ? ' · ' + esc(f.reg) : '') + '<br>' +
        (f.nm || 0) + ' nm · ' + fmtDur(f.min || 0) + (f.lr != null ? ' · ' + f.lr + ' fpm' : '') + (f.track ? '' : '<br><span style="color:#94a3b8">' + T.fNoTrack + '</span>');
      L.popup({ maxWidth: 260 }).setLatLng(latlng).setContent(h).openOn(map);
    }
    function selectFlight(id, zoom) {
      if (selId && flLayers[selId]) {
        var o = flLayers[selId]; o.grp.eachLayer(function (l, i) { if (l.setStyle && l instanceof L.Polyline && !(l instanceof L.CircleMarker)) { l.setStyle({ color: o.col }); } });
        o.grp.getLayers().forEach(function (l, i) { if (l.setStyle) { l.setStyle(o.f.track ? { weight: i === 0 ? 6 : 1.6, opacity: i === 0 ? 0.12 : 0.85 } : { weight: 1, opacity: 0.3 }); } });
      }
      Array.prototype.forEach.call(document.querySelectorAll('.fmp-row.sel'), function (e) { e.classList.remove('sel'); });
      selId = id; var n = flLayers[id]; if (!n) { return; }
      n.grp.getLayers().forEach(function (l, i) { if (l.setStyle) { l.setStyle(n.f.track ? { color: '#ffffff', weight: i === 0 ? 8 : 3, opacity: i === 0 ? 0.25 : 1 } : { color: '#ffffff', weight: 2, opacity: 0.9 }); } if (l.bringToFront) { l.bringToFront(); } });
      var row = document.querySelector('.fmp-row[data-id="' + id + '"]'); if (row) { row.classList.add('sel'); }
      if (zoom) { try { var bb = n.grp.getBounds(); map.fitBounds(bb, { padding: [60, 60], maxZoom: 9 }); openFlightPopup(n.f, bb.getCenter()); } catch (e) {} }
    }

    function renderHelp() {
      var rows = mode === 'flown' ? T.helpFlown : T.helpVisited, h = '<h6>' + (mode === 'flown' ? T.helpTitleFlown : T.helpTitleVisited) + '</h6>';
      rows.forEach(function (r) {
        var i = r.indexOf('|'), k = r.slice(0, i), t = r.slice(i + 1), sw;
        if (k === 'green') { sw = '<i class="dot" style="background:#34d399;box-shadow:0 0 0 2px #ecfdf5"></i>'; }
        else if (k === 'grey') { sw = '<i class="dot" style="background:#64748b"></i>'; }
        else if (k === 'line') { sw = '<i class="ln"></i>'; }
        else if (k === 'dash') { sw = '<i class="ln dash"></i>'; }
        else if (k === 'tap') { sw = '<i class="ph-fill ph-hand-pointing" style="font-size:18px;color:#94a3b8"></i>'; }
        else { sw = '<i class="ph-fill ph-info" style="font-size:16px;color:#94a3b8"></i>'; }
        var parts = t.split('::');
        h += '<div class="r"><span class="sw">' + sw + '</span><span><b>' + esc(parts[0]) + '</b>' + (parts[1] ? '<div class="tx">' + esc(parts[1]) + '</div>' : '') + '</span></div>';
      });
      document.getElementById('fmp-help').innerHTML = h;
    }
    document.getElementById('fmp-help-btn').addEventListener('click', function () {
      var box = document.getElementById('fmp-help'), open = box.classList.contains('fmp-hide');
      if (open) { renderHelp(); }
      box.classList.toggle('fmp-hide', !open);
      this.classList.toggle('on', open); this.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // preload the flown data in the background so switching is instant
    var flightsReq = null;
    function loadFlights() {
      if (!flightsReq) { flightsReq = phpvms.request({ url: '{{ url('/userflightmap/tracks/'.$user->id) }}' }).then(function (r) { flights = r.data.flights || []; return flights; }); }
      return flightsReq;
    }
    setTimeout(loadFlights, 1500);
    function setMode(m) {
      mode = m;
      if (!document.getElementById('fmp-help').classList.contains('fmp-hide')) { setTimeout(renderHelp, 0); }
      document.getElementById('fmp-hint').textContent = m === 'flown' ? T.hintFlown : T.hintVisited;
      var flown = m === 'flown';
      document.getElementById('fmp-m-visited').classList.toggle('on', !flown);
      document.getElementById('fmp-m-flown').classList.toggle('on', flown);
      document.getElementById('fmp-tools-visited').classList.toggle('fmp-hide', flown);
      document.getElementById('fmp-pcard').classList.toggle('fmp-flown', flown);
      [[gLines, 'fmp-t-lines'], [gOpen, 'fmp-t-open'], [gVisited, null]].forEach(function (x) {
        var on = flown ? false : (x[1] ? document.getElementById(x[1]).classList.contains('on') : true);
        if (on) { x[0].addTo(map); } else { map.removeLayer(x[0]); }
      });
      if (flown) {
        gFlown.addTo(map);
        if (flights) { renderFlown(); }
        else {
          document.getElementById('fmp-stats').innerHTML = '<div class="fmp-stat">' + T.fLoading + '</div>';
          loadFlights().then(function () { if (mode === 'flown') { renderFlown(); } });
        }
      } else {
        map.removeLayer(gFlown);
        document.getElementById('fmp-stats').innerHTML = visitedHtml;
        try { var b = gVisited.getBounds(); if (b.isValid()) { map.fitBounds(b, { padding: [40, 40], maxZoom: 6 }); } } catch (e) {}
      }
      setTimeout(function () { map.invalidateSize(); }, 30);
    }
    document.getElementById('fmp-hint').textContent = T.hintVisited;
    document.getElementById('fmp-m-visited').addEventListener('click', function () { setMode('visited'); });
    document.getElementById('fmp-m-flown').addEventListener('click', function () { setMode('flown'); });
    window.fmpMap = map;
  }

  // Leaflet needs a visible container: start when the tab opens (also if it is restored as active tab)
  function hook() {
    var tab = document.querySelector('a[href="#userflightmap"]');
    if (!tab) { return; }
    tab.addEventListener('shown.bs.tab', function () { start(); if (window.fmpMap) { setTimeout(function () { window.fmpMap.invalidateSize(); }, 50); } });
    if (document.getElementById('userflightmap').classList.contains('active')) { start(); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', hook); } else { hook(); }
  window.addEventListener('load', function () { var p = document.getElementById('userflightmap'); if (p && p.classList.contains('active')) { start(); } });
})();
</script>
