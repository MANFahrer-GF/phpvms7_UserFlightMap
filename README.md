# phpvms7_UserFlightMap

A **"Flight map" tab for the phpVMS 7 pilot profile**: shows which airports of your
network a pilot has already been to — and which are still open.

- **Visited** airports (departure or arrival of an accepted PIREP) as green dots, sized by number of flights
- **Not yet visited** airports of the network (every airport that appears in an active flight of the schedule) as small grey dots
- Stats: airports visited, share of the network (progress bar), countries, routes
- Toggles for the route lines (straight great-circle A→B) and the open airports
- Works on every pilot's profile, dark CARTO map, DE/EN

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

The theme needs Bootstrap tabs, Leaflet (`L`), `L.Geodesic` and `phpvms.request` — all part of the stock phpVMS 7 frontend bundle.

## How it works

`GET /userflightmap/profile/{id}` (auth required) returns the pilot's visited airports,
the open airports of the network and the aggregated routes. The map starts when the tab is
first shown (Leaflet needs a visible container).

Companion to [phpvms7_FlightMap](https://github.com/MANFahrer-GF/phpvms7_FlightMap). MIT licensed.
