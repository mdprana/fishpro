<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::withCount('receivings')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('code')
            ->get();

        return view('master.suppliers', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();

        $supplier = Supplier::create($data);

        return back()->with('success', "Supplier {$supplier->display_name} ditambahkan.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validated($request, $supplier);
        $data['is_active']  = $request->boolean('is_active');
        $data['updated_by'] = auth()->id();

        $supplier->update($data);

        return back()->with('success', "Supplier {$supplier->display_name} diperbarui.");
    }

    /**
     * Supplier tidak pernah dihapus permanen karena kodenya sudah tertanam di
     * nomor batch penerimaan lama, jadi cukup dinonaktifkan agar hilang
     * dari dropdown tanpa memutus jejak audit.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => false, 'updated_by' => auth()->id()]);

        return back()->with('success', "Supplier {$supplier->display_name} dinonaktifkan.");
    }

    public function lookup(Request $request): JsonResponse
    {
        $suppliers = Supplier::active()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->orderBy('code')
            ->limit(30)
            ->get(['id', 'code', 'name', 'city']);

        return response()->json($suppliers);
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $request->merge(['code' => Supplier::normalizeCode($request->input('code'))]);

        return $request->validate([
            'code' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('suppliers', 'code')->ignore($supplier?->id),
            ],
            'name'           => 'required|string|max:200',
            'contact_person' => 'nullable|string|max:150',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:150',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ], [
            'code.regex' => 'Kode supplier hanya boleh huruf dan angka (dipakai di nomor batch).',
        ]);
    }
}
