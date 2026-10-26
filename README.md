# 🐟 FishPro — Fish Processing Management System

Laravel 11 + PHP 8.2 application for managing fish/poultry/seafood processing operations: Purchase Orders, raw material receiving, production sessions, and packing — with yield estimation and surplus tracking.

## Quick Start

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed  
php artisan serve
```

Login: `admin@fishpro.com` / `password`

## Data Structure (v2 — Group/Subgroup model)

### Master Data
- **Material Group** (`material_groups`) — e.g. *YF LOIN*, *BE LOIN*, *Ayam Beku*
  - Fields: name, unit, color, description (no yield % here anymore)
  - **Material Subgroup** (`material_subgroups`) — actual stocked items, e.g. *YF LOIN EROPA WARNA*, *Ayam Boiler*
- **Product Group** (`product_groups`) — e.g. *Ikan*, *Ayam*, *Seafood*, *Frozen Food*, *Ready To Cook*
  - **Product Type** (`product_types`) — e.g. *BE STEAK*, *YF LOIN*, *Dada Ayam* (color optional, falls back to group color)
- **Yield Estimate** (`yield_estimates`) — link table: Subgroup → Product Type with a percentage. A subgroup's percentages should sum to 100% (UI warns if not). Used to project expected output when receiving raw material.

### Transactional Flow
1. **Purchase Order** (`purchase_orders` + `po_items`) — multi-product PO. Each item: Product Group → Product Type, then Target Weight / Target Box / Weight-per-Box — fill any two, the third auto-calculates.
2. **Receiving** (`receivings`) — pick Group → Subgroup, enter weight + container info. Stock (`raw_material_stocks`) increments automatically. The configured yield estimates for that subgroup are shown live, projecting expected product output.
3. **Production Session** (`production_sessions` + `production_outputs`) — one session draws from one subgroup's stock (decremented automatically) and can produce *multiple* product outputs in one go (e.g. 10kg chicken → breast + thigh + wing + bone).
4. **Packing** (`packing_results`) — pack a specific product against its PO item; validated against available semi-finished stock (`production output - already packed`).
5. **Surplus** (`po_surpluses`) — when packing exceeds a PO item's target box count, the excess is automatically logged as a surplus tied to the source PO, for later reallocation.

### Dashboard
- Per-product (not global) progress bars for both **Process %** and **Packing %**, each PO item shown separately.
- Early warning list flags any product under 100% on either process or packing, plus low-stock subgroups.
- Stock summary grouped by Material Group → Subgroup.
- 7-day output chart, today's per-product output stats, and active surplus list.

## Key Routes
| Area | Path |
|---|---|
| Dashboard | `/dashboard` |
| Purchase Orders | `/po` |
| Receiving | `/receiving` |
| Production | `/production` |
| Packing | `/packing` |
| Material Groups (master) | `/master/material-groups` |
| Product Groups (master) | `/master/product-groups` |

## Notes
- Container type on receiving is a free-text field with datalist suggestions (not a fixed enum).
- All weight figures are stored as `decimal` and displayed with 1–2 decimal places (no trailing `.0000`).
- Soft deletes are used on `purchase_orders`, `receivings`, `production_sessions`, `packing_results` — deleting reverses stock effects automatically via model `boot()` hooks.
