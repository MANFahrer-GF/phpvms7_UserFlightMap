{{-- Profile tab "Flugkarte": visited vs. still-open airports of the GSG network. Expects $user. --}}
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
</style>
<div class="fmp-card">
  <div class="fmp-head">
    <div class="fmp-stats" id="fmp-stats"></div>
    <div class="fmp-bar"><i id="fmp-bar" style="width:0"></i></div>
    <div class="fmp-tools">
      <button type="button" class="fmp-pill on" id="fmp-t-lines"><i class="ph-fill ph-line-segments"></i>@lang('userflightmap::messages.p_routes')</button>
      <button type="button" class="fmp-pill on" id="fmp-t-open"><i class="ph-fill ph-circle-dashed"></i>@lang('userflightmap::messages.p_open')</button>
      <span class="fmp-legend">
        <span><span class="fmp-dot" style="background:#34d399"></span> @lang('userflightmap::messages.p_visited')</span>
        <span><span class="fmp-dot" style="background:#64748b"></span> @lang('userflightmap::messages.p_open')</span>
      </span>
    </div>
  </div>
  <div id="fmp-map"></div>
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
    visited:         @json(__('userflightmap::messages.p_visited'))
  };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }

  function start() {
    if (started) { return; }
    started = true;
    var map = L.map('fmp-map', { worldCopyJump: true, minZoom: 2, scrollWheelZoom: true });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 18 }).addTo(map);
    map.setView([30, 10], 2);
    var gLines = L.featureGroup().addTo(map), gOpen = L.featureGroup().addTo(map), gVisited = L.featureGroup().addTo(map);

    phpvms.request({ url: '{{ url('/userflightmap/profile/'.$user->id) }}' }).then(function (r) {
      var d = r.data, s = d.stats;
      var pct = s.network ? Math.round(100 * s.network_visited / s.network) : 0;
      document.getElementById('fmp-stats').innerHTML =
        '<div class="fmp-stat"><b>' + s.visited + '</b>' + T.visitedAirports + '</div>' +
        '<div class="fmp-stat"><b>' + s.network_visited + ' / ' + s.network + ' (' + pct + '%)</b>' + T.ofNetwork + '</div>' +
        '<div class="fmp-stat"><b>' + s.countries + '</b>' + T.countries + '</div>' +
        '<div class="fmp-stat"><b>' + s.routes + '</b>' + T.routes + '</div>';
      document.getElementById('fmp-bar').style.width = pct + '%';

      var maxC = 1; d.lines.forEach(function (l) { if (l[6] > maxC) { maxC = l[6]; } });
      d.lines.forEach(function (l) {
        var g = new L.Geodesic([], { color: '#22d3ee', weight: 1 + Math.round(3 * Math.sqrt(l[6]) / Math.sqrt(maxC)), opacity: 0.4, wrap: false });
        g.setLatLngs([[l[0], l[1]], [l[2], l[3]]]);
        g.bindTooltip(esc(l[4]) + ' → ' + esc(l[5]) + ' · ' + l[6] + '×', { sticky: true, className: 'fm-tt' });
        g.addTo(gLines);
      });
      d.open.forEach(function (a) {
        var m = L.circleMarker([a[2], a[3]], { radius: 2.6, color: '#0b0f17', weight: 0.5, fillColor: '#64748b', fillOpacity: 0.75, bubblingMouseEvents: false });
        m.bindTooltip('<b>' + esc(a[0]) + '</b> ' + esc(a[1]) + '<br>' + T.notYet, { className: 'fm-tt' });
        m.addTo(gOpen);
      });
      d.visited.forEach(function (a) {
        var m = L.circleMarker([a[2], a[3]], { radius: 5 + Math.min(4, Math.log2(a[4] + 1)), color: '#ecfdf5', weight: 1.2, fillColor: '#34d399', fillOpacity: 1, bubblingMouseEvents: false });
        m.bindTooltip('<b>' + esc(a[0]) + '</b> ' + esc(a[1]) + '<br>' + a[4] + ' ' + T.flights, { className: 'fm-tt' });
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
