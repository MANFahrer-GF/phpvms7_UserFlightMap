# phpvms7_UserFlightMap

A **"Flight map" tab for the phpVMS 7 pilot profile**: shows which airports of your
network a pilot has already been to — and which are still open.

Two views, each with a built-in help panel (DE/EN):

- **Visited & not flown to** – green = airports the pilot has flown to (accepted PIREP, departure or arrival, sized by flights); grey = airports of the network (every airport with active flights in the schedule) never flown to. Stats: airports visited, share of the network, countries. Toggles for route lines and grey dots.
- **Flown** – Volanta-style history: every accepted flight as a glowing line in the colour of its year, using the **real recorded ACARS track** (thinned to ~70 points, cached per flight); flights without stored positions are drawn dashed as a straight line. Year filter, totals (flights, nm, hours) and a flight list; tap a line or entry for date, type, distance, duration and landing rate.
- Works on touch devices (tap popups), on every pilot's profile, dark map, DE/EN.

## Install

1. Copy this repo's content to `modules/UserFlightMap/` (or unzip the release; the ZIP has one top folder `UserFlightMap/`).
2. Enable the module (Admin → Modules) and run `php artisan route:clear && php artisan view:clear`.
3. Add the tab to your profile view (`resources/views/layouts/<theme>/profile/index.blade.php`):

```blade
{{-- in the tab list --}}
<li class="nav-item" role="presentation">
  <a class="nav-link" data-bs-toggle="tab" href="#userflightmap" role="tab">Flight map</a>
</li>

{{-- in the tab content --}}
<div class="tab-pane" id="userflightmap" role="tabpanel">
  @include('userflightmap::partials.profile_map', ['user' => $user])
</div>
```

Basemap: CARTO dark. It reads the key from the phpVMS setting `acars.carto_api_key` (or env `CARTO_API_KEY`) and falls back to the key-free Esri Dark Gray map if none is set.

The theme needs Bootstrap tabs, Leaflet (`L`), `L.Geodesic` and `phpvms.request` — all part of the stock phpVMS 7 frontend bundle.

## How it works

`GET /userflightmap/profile/{id}` and `GET /userflightmap/tracks/{id}` (auth required) return the visited/open airports with routes, and the flown flights with thinned tracks.
Tracks are cached per flight in the file cache store (the cache never needs invalidation, a finished flight does not change). The map starts when the tab is
first shown (Leaflet needs a visible container).

Companion to [phpvms7_FlightMap](https://github.com/MANFahrer-GF/phpvms7_FlightMap). MIT licensed.
