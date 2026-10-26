<?php

namespace App\Providers;

use App\Models\PackingBox;
use App\Models\PoItem;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Data ringan untuk sidebar: hanya query agregat, tanpa memuat model per baris.
        View::composer('layouts.app', function ($view) {
            $activeIds = PurchaseOrder::active()->select('id');

            $view->with('navSidebar', [
                'overdue_po'  => PurchaseOrder::active()->whereDate('deadline', '<', today())->count(),
                'packed_box'  => PackingBox::active()->whereIn('purchase_order_id', $activeIds)->count(),
                'target_box'  => (int) PoItem::whereIn('purchase_order_id', $activeIds)->sum('target_box'),
            ]);
        });
    }
}