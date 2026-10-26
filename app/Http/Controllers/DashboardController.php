<?php
namespace App\Http\Controllers;
use App\Services\DashboardService;
use App\Services\YieldService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $svc,
        private YieldService $yield,
    ) {
    }

    public function index(): View
    {
        return view('dashboard.index', [
            'summary'      => $this->svc->getSummary(),
            'weeklyChart'  => $this->svc->getWeeklyChart(),
            'dailyStats'   => $this->svc->getDailyStats(),
            'stockSummary' => $this->svc->getStockSummary(),
            'poTable'      => $this->svc->getPOTable(),
            'surpluses'    => $this->svc->getSurpluses(),
            'recovery'     => $this->yield->summary(),
            // Lima alert teratas; selebihnya di halaman Yield & Recovery.
            'yieldAlerts'  => $this->yield->alerts(limit: 5),
        ]);
    }
}
