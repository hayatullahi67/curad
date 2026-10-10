<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\Watchlist;
use App\Services\Maps\RoadDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The marketplace. It is shown in two places with the same contents: on the
 * public website, where nobody has to sign in, and inside the customer's
 * dashboard. Everything comes from the same product records the POS sells
 * from, so the marketplace never has its own copy of stock.
 *
 * "Near me": once a shopper shares where they are, products can be sorted by
 * the closest pharmacy that sells them and limited to a distance. Distances
 * are road distances (see RoadDistance). The position is kept in the
 * session only.
 */
class MarketplaceController extends Controller
{
    private const PER_PAGE = 24;

    private const SORTS = [
        'newest' => 'Newest first',
        'nearest' => 'Nearest to me',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'name' => 'Name: A to Z',
    ];

    /** Distances, in kilometres, a shopper can limit the marketplace to. */
    private const DISTANCES = [5, 10, 25, 50];

    /** Road distance from the shopper to each pharmacy, worked out once per request. */
    private ?array $roadMap = null;

    public function __construct(private RoadDistance $roads)
    {
    }

    public function home()
    {
        return view('home', [
            'featured' => $this->products($this->offers()->whereRaw('stock_level > reserved_stock + expired_stock'))
                ->orderByDesc('listed_at')->limit(8)->get(),
            'categories' => $this->categories(),
            'stats' => [
                'products' => $this->offers()->distinct()->count(DB::raw('LOWER(TRIM(name))')),
                'pharmacies' => Pharmacy::active()->count(),
            ],
        ]);
    }

    /** The newest in-stock products, for the customer's overview page. */
    public function fresh(int $limit)
    {
        return $this->products($this->offers()->whereRaw('stock_level > reserved_stock + expired_stock'), $this->here())
            ->orderByDesc('listed_at')->limit($limit)->get();
    }

    public function index(Request $request)
    {
        if ($redirect = $this->rememberLocation($request)) {
            return $redirect;
        }

        $here = $this->here();
        $search = trim((string) $request->query('q'));
        $category = trim((string) $request->query('category'));
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? $request->query('sort') : 'newest';
        $inStockOnly = $request->boolean('in_stock');
        $within = $here && in_array((int) $request->query('within'), self::DISTANCES, true) ? (int) $request->query('within') : null;

        // "Nearest" means nothing until we know where the shopper is.
        if ($sort === 'nearest' && ! $here) {
            $sort = 'newest';
        }

        $offers = $this->offers();

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $offers->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('dosage', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($category !== '') {
            $offers->where('category', $category);
        }

        if ($inStockOnly) {
            $offers->whereRaw('stock_level > reserved_stock + expired_stock');
        }

        if ($within) {
            $offers->whereRaw($this->distanceSql($here) . ' <= ?', [$within]);
        }

        $products = $this->products($offers, $here);

        match ($sort) {
            // Worked out again here because the database cannot sort on a test of the nearest_km name.
            // Products with no pharmacy on the map go last.
            'nearest' => $products->orderByRaw('COALESCE(MIN(' . $this->distanceSql($here) . '), 999999)'),
            'price_low' => $products->orderBy('min_price'),
            'price_high' => $products->orderByDesc('min_price'),
            'name' => $products->orderBy('name'),
            default => $products->orderByDesc('listed_at'),
        };

        return view('marketplace.index', $this->place($request) + [
            'products' => $products->orderBy('id')->paginate(self::PER_PAGE)->withQueryString(),
            'categories' => $this->categories(),
            'sorts' => $here ? self::SORTS : array_diff_key(self::SORTS, ['nearest' => true]),
            'distances' => self::DISTANCES,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'inStockOnly' => $inStockOnly,
            'within' => $within,
            'here' => $here,
        ]);
    }

    public function show(Request $request, Medication $medication)
    {
        // Only products a pharmacy has put on the marketplace have a page.
        abort_unless($this->offers()->whereKey($medication->id)->exists(), 404);

        $here = $this->here();
        $nameKey = mb_strtolower(trim($medication->name));

        $offers = $this->offers()->with('pharmacy')->whereRaw('LOWER(TRIM(name)) = ?', [$nameKey])->get()
            ->each(function (Medication $offer) use ($here) {
                $trip = $here ? ($this->roadMap($here)[$offer->pharmacy_id] ?? null) : null;
                $offer->distance_km = $trip['km'] ?? null;
                $offer->distance_by_road = $trip['road'] ?? false;
            });

        // In stock first. After that: the closest pharmacy when we know where
        // the shopper is, otherwise the cheapest.
        $offers = $offers->sort(function (Medication $a, Medication $b) use ($here) {
            return [$b->availableStock() > 0, $here ? ($a->distance_km ?? PHP_FLOAT_MAX) : 0, (float) $a->price]
                <=> [$a->availableStock() > 0, $here ? ($b->distance_km ?? PHP_FLOAT_MAX) : 0, (float) $b->price];
        })->values();

        $related = $this->products(
            $this->offers()
                ->where('category', $medication->category)
                ->whereRaw('LOWER(TRIM(name)) <> ?', [$nameKey])
                ->whereRaw('stock_level > reserved_stock + expired_stock'),
            $here
        )->orderByDesc('listed_at')->limit(4)->get();

        $user = Auth::user();

        return view('marketplace.show', $this->place($request) + [
            'product' => $medication,
            'offers' => $offers,
            'related' => $related,
            'here' => $here,
            'watching' => $user ? Watchlist::where('user_id', $user->id)->whereIn('medication_id', $offers->pluck('id'))->first() : null,
            'savedPharmacyIds' => $user ? $user->savedPharmacies()->pluck('pharmacies.id')->all() : [],
        ]);
    }

    /**
     * Where the marketplace is being shown: on the website or inside the
     * customer's dashboard. The pages are the same; only the frame around
     * them and the addresses of their links change.
     */
    private function place(Request $request): array
    {
        $inDashboard = $request->routeIs('user.marketplace', 'user.marketplace.*');

        return [
            'inDashboard' => $inDashboard,
            'marketRoute' => $inDashboard ? 'user.marketplace' : 'marketplace',
            'productRoute' => $inDashboard ? 'user.marketplace.show' : 'marketplace.show',
        ];
    }

    /**
     * Takes a position sent by the "Near me" button (or the request to
     * forget it), keeps it in the session, and sends the shopper back to a
     * clean address without the coordinates in it.
     */
    private function rememberLocation(Request $request)
    {
        $rest = $request->except(['lat', 'lng', 'near', 'page']);

        if ($request->query('near') === 'off') {
            $request->session()->forget('shopper_location');
            unset($rest['within']);

            if (($rest['sort'] ?? null) === 'nearest') {
                unset($rest['sort']);
            }

            return redirect()->route($request->route()->getName(), $rest);
        }

        if (! $request->has(['lat', 'lng'])) {
            return null;
        }

        $latitude = filter_var($request->query('lat'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($request->query('lng'), FILTER_VALIDATE_FLOAT);

        if ($latitude !== false && $longitude !== false && abs($latitude) <= 90 && abs($longitude) <= 180) {
            $request->session()->put('shopper_location', ['lat' => round($latitude, 5), 'lng' => round($longitude, 5)]);
        }

        return redirect()->route($request->route()->getName(), $rest);
    }

    /** Where the shopper said they are, or null when they have not. */
    private function here(): ?array
    {
        $location = session('shopper_location');

        return is_array($location) && isset($location['lat'], $location['lng']) ? $location : null;
    }

    /**
     * How far the shopper would drive to each pharmacy that is on the map:
     * pharmacy id => ['km' => ..., 'road' => true, or false for an estimate].
     */
    private function roadMap(array $here): array
    {
        return $this->roadMap ??= $this->roads->fromPoint(
            $here['lat'],
            $here['lng'],
            Pharmacy::active()->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'latitude', 'longitude'])
                ->mapWithKeys(fn (Pharmacy $pharmacy) => [$pharmacy->id => [(float) $pharmacy->latitude, (float) $pharmacy->longitude]])
                ->all(),
        );
    }

    /**
     * The same distances written for the database, so products can be
     * sorted and filtered by them: the kilometres to the pharmacy that
     * holds an offer, or NULL for a pharmacy with no map position. Only
     * numbers we produced ourselves go into it.
     */
    private function distanceSql(array $here): string
    {
        $cases = collect($this->roadMap($here))
            ->map(fn (array $trip, $pharmacyId) => 'WHEN ' . (int) $pharmacyId . ' THEN ' . number_format((float) $trip['km'], 1, '.', ''))
            ->implode(' ');

        return $cases === '' ? 'NULL' : "(CASE medications.pharmacy_id {$cases} END)";
    }

    /**
     * Every product a pharmacy is offering online right now: listed for
     * sale, switched on for the marketplace, not expired, and belonging to
     * a pharmacy that still exists.
     */
    private function offers()
    {
        return Medication::query()
            ->marketplace()
            ->whereHas('pharmacy', fn ($branch) => $branch->active())
            ->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            });
    }

    /**
     * Turns offers into the catalogue: the same product sold by several
     * pharmacies is one card, showing the lowest price, how many pharmacies
     * have it and, when we know where the shopper is, how far the closest
     * one is.
     */
    private function products($offers, ?array $here = null)
    {
        $offers
            ->selectRaw('LOWER(TRIM(name)) as name_key')
            ->selectRaw('MIN(id) as id, MIN(name) as name, MAX(category) as category, MAX(dosage) as dosage')
            ->selectRaw('MAX(photo) as photo, MAX(is_rx) as is_rx, MAX(listed_at) as listed_at')
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->selectRaw('COUNT(DISTINCT pharmacy_id) as pharmacy_count')
            ->selectRaw('SUM(GREATEST(CAST(stock_level AS SIGNED) - CAST(reserved_stock AS SIGNED) - CAST(expired_stock AS SIGNED), 0)) as total_stock');

        $here
            ? $offers->selectRaw('MIN(' . $this->distanceSql($here) . ') as nearest_km')
            : $offers->selectRaw('NULL as nearest_km');

        return $offers->groupBy('name_key');
    }

    /** Categories that have at least one product online, biggest first. */
    private function categories()
    {
        return $this->offers()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->selectRaw('category, COUNT(DISTINCT LOWER(TRIM(name))) as product_count')
            ->groupBy('category')
            ->orderByDesc('product_count')
            ->orderBy('category')
            ->get()
            ->map(fn ($row) => (object) [
                'name' => $row->category,
                'count' => (int) $row->product_count,
                'icon' => Medication::categoryIcon($row->category),
            ]);
    }
}
