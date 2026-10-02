<?php

namespace Modules\UserFlightMap\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Models\Airport;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProfileMapController extends Controller
{
    /**
     * Profile tab: where has this pilot been (accepted PIREPs, dep or arr) and
     * which airports of the GSG network are still open. "Network" = every
     * airport that appears in an active flight of the schedule.
     */
    public function profile(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $rows = Pirep::where('state', PirepState::ACCEPTED)
            ->where('user_id', $user->id)
            ->whereNotNull('dpt_airport_id')
            ->whereNotNull('arr_airport_id')
            ->select('dpt_airport_id', 'arr_airport_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('dpt_airport_id', 'arr_airport_id')
            ->get();

        // visits per airport (a flight counts once for each end)
        $visits = [];
        foreach ($rows as $r) {
            if ($r->dpt_airport_id === $r->arr_airport_id) {
                $visits[$r->dpt_airport_id] = ($visits[$r->dpt_airport_id] ?? 0) + (int) $r->cnt;
                continue;
            }
            $visits[$r->dpt_airport_id] = ($visits[$r->dpt_airport_id] ?? 0) + (int) $r->cnt;
            $visits[$r->arr_airport_id] = ($visits[$r->arr_airport_id] ?? 0) + (int) $r->cnt;
        }

        $network = DB::table('flights')->where('active', 1)->pluck('dpt_airport_id')
            ->merge(DB::table('flights')->where('active', 1)->pluck('arr_airport_id'))
            ->filter()->unique()->values()->all();

        $ids = array_values(array_unique(array_merge($network, array_keys($visits))));
        $apts = Airport::whereIn('id', $ids)->get(['id', 'icao', 'name', 'lat', 'lon', 'country'])->keyBy('id');

        $visited = [];
        $open = [];
        $countries = [];
        foreach ($apts as $a) {
            if ($a->lat === null || $a->lon === null || ((float) $a->lat == 0.0 && (float) $a->lon == 0.0)) {
                continue;
            }
            if (isset($visits[$a->id])) {
                $visited[] = [$a->icao, $a->name, (float) $a->lat, (float) $a->lon, $visits[$a->id], $a->country];
                if ($a->country) {
                    $countries[$a->country] = true;
                }
            } else {
                $open[] = [$a->icao, $a->name, (float) $a->lat, (float) $a->lon];
            }
        }

        $lines = [];
        foreach ($rows as $r) {
            $d = $apts->get($r->dpt_airport_id);
            $a = $apts->get($r->arr_airport_id);
            if (!$d || !$a || $d->id === $a->id || $d->lat === null || $a->lat === null) {
                continue;
            }
            $lines[] = [(float) $d->lat, (float) $d->lon, (float) $a->lat, (float) $a->lon, $d->icao, $a->icao, (int) $r->cnt];
        }

        $networkVisited = count(array_intersect($network, array_keys($visits)));

        return response()->json([
            'visited' => $visited,
            'open'    => $open,
            'lines'   => $lines,
            'stats'   => [
                'visited'         => count($visited),
                'network'         => count($network),
                'network_visited' => $networkVisited,
                'countries'       => count($countries),
                'routes'          => count($lines),
            ],
        ]);
    }

    /**
     * Volanta-style history: every accepted flight of the pilot with its real
     * ACARS track (thinned to ~90 points) or, for flights without positions,
     * just the endpoints (the frontend draws a great-circle fallback).
     */
    public function tracks(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $flights = DB::table('pireps as p')
            ->leftJoin('airports as d', 'd.id', '=', 'p.dpt_airport_id')
            ->leftJoin('airports as a', 'a.id', '=', 'p.arr_airport_id')
            ->leftJoin('aircraft as ac', 'ac.id', '=', 'p.aircraft_id')
            ->where('p.user_id', $user->id)
            ->where('p.state', PirepState::ACCEPTED)
            ->whereNull('p.deleted_at')
            ->orderByDesc('p.submitted_at')
            ->get([
                'p.id', 'p.submitted_at', 'p.created_at', 'p.flight_time', 'p.distance', 'p.landing_rate',
                'd.icao as dpt', 'd.lat as dlat', 'd.lon as dlon', 'd.name as dname',
                'a.icao as arr', 'a.lat as alat', 'a.lon as alon', 'a.name as aname',
                'ac.icao as type', 'ac.registration as reg',
            ]);

        // GSG runs with CACHE_DRIVER=null, so use the file store explicitly. A finished
        // flight's track never changes -> cache the thinned track per flight (forever).
        $store = Cache::store('file');
        $ids = $flights->pluck('id')->all();
        $keyOf = fn ($id) => 'ufm.t1.'.$id;
        $got = $store->many(array_map($keyOf, $ids));
        $tracks = [];
        $missing = [];
        foreach ($ids as $id) {
            $v = $got[$keyOf($id)] ?? null;
            if ($v === null) {
                $missing[] = $id;
            } elseif ($v !== 0) {
                $tracks[$id] = $v;
            }
        }

        if ($missing) {
            $counts = DB::table('acars')->where('type', 0)->whereIn('pirep_id', $missing)
                ->select('pirep_id', DB::raw('COUNT(*) as c'))->groupBy('pirep_id')->pluck('c', 'pirep_id');
            $step = [];
            foreach ($counts as $pid => $c) {
                $step[$pid] = max(1, (int) floor($c / 70));
            }

            $new = [];
            $seen = [];
            $last = [];
            $rows = DB::table('acars')->where('type', 0)->whereIn('pirep_id', array_keys($step))
                ->whereNotNull('lat')->whereNotNull('lon')
                ->orderBy('pirep_id')->orderBy('created_at')->orderBy('id')
                ->select('pirep_id', 'lat', 'lon')->cursor();
            foreach ($rows as $r) {
                $n = $seen[$r->pirep_id] = ($seen[$r->pirep_id] ?? 0) + 1;
                $pt = [round((float) $r->lat, 3), round((float) $r->lon, 3)];
                $last[$r->pirep_id] = $pt;
                if (($n - 1) % $step[$r->pirep_id] === 0) {
                    $new[$r->pirep_id][] = $pt;
                }
            }
            foreach ($last as $pid => $pt) {
                if (end($new[$pid]) !== $pt) {
                    $new[$pid][] = $pt;
                }
            }
            foreach ($missing as $id) {
                $t = $new[$id] ?? null;
                $store->forever($keyOf($id), $t ?: 0);
                if ($t) {
                    $tracks[$id] = $t;
                }
            }
        }

        $list = [];
        foreach ($flights as $f) {
            if ($f->dlat === null || $f->alat === null) {
                continue;
            }
            $t = $tracks[$f->id] ?? null;
            $dist = $f->distance;
            if (is_string($dist) && str_starts_with($dist, '{')) {
                $dist = json_decode($dist, true)['nmi'] ?? null;
            }
            $list[] = [
                'id'    => $f->id,
                'date'  => substr((string) ($f->submitted_at ?: $f->created_at), 0, 10),
                'dpt'   => $f->dpt, 'arr' => $f->arr,
                'dname' => $f->dname, 'aname' => $f->aname,
                'from'  => [(float) $f->dlat, (float) $f->dlon],
                'to'    => [(float) $f->alat, (float) $f->alon],
                'type'  => $f->type, 'reg' => $f->reg,
                'min'   => (int) $f->flight_time,
                'nm'    => $dist !== null ? (int) round((float) $dist) : null,
                'lr'    => $f->landing_rate !== null ? (int) round((float) $f->landing_rate) : null,
                'track' => ($t && count($t) > 4) ? $t : null,
            ];
        }

        return response()->json(['flights' => $list]);
    }
}
