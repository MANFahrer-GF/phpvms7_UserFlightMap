<?php

namespace Modules\UserFlightMap\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Models\Airport;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
