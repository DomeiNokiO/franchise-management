<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(): View
    {
        return view('catalog.ingredients.index');
    }

    /** Endpoint serverside DataTables. */
    public function data(): JsonResponse
    {
        return DataTables::process(
            Ingredient::query()->withCount('stocks'),
            ['name', 'sku', 'cost', 'reorder_point', 'created_at'],
            function ($query, string $search) {
                if ($search !== '') {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                }
                return $query;
            },
            fn (Ingredient $ingredient) => [
                'id' => $ingredient->id,
                'sku' => $ingredient->sku,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'cost' => 'Rp '.number_format((float) $ingredient->cost, 0, ',', '.'),
                'reorder_point' => (float) $ingredient->reorder_point,
                'is_active' => (bool) $ingredient->is_active,
            ]
        );
    }

    /** Fragment form untuk modal (baru bila tanpa id). */
    public function form(?int $ingredient = null): \Illuminate\Contracts\View\View
    {
        $this->ownerOnly();
        $model = $ingredient !== null ? Ingredient::findOrFail($ingredient) : new Ingredient;
        return view('catalog.fragments.ingredient-form', ['ingredient' => $model]);
    }

    public function create(): View
    {
        $this->ownerOnly();
        return view('catalog.fragments.ingredient-form', ['ingredient' => new Ingredient]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ownerOnly();
        Ingredient::create($this->validated($request));
        return redirect()->route('ingredients.index')->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('catalog.fragments.ingredient-form', compact('ingredient'));
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->ownerOnly();
        $ingredient->update($this->validated($request, $ingredient));
        return redirect()->route('ingredients.index')->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $this->ownerOnly();
        if ($ingredient->recipes()->exists() || $ingredient->purchaseOrderItems()->exists() || $ingredient->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->withErrors(['ingredient' => 'Bahan sudah dipakai atau masih memiliki stok. Nonaktifkan saja.']);
        }
        $ingredient->delete();
        return redirect()->route('ingredients.index')->with('success', 'Bahan baku dihapus.');
    }

    private function validated(Request $request, ?Ingredient $ingredient = null): array
    {
        $id = $ingredient?->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('ingredients', 'sku')->ignore($id)],
            'unit' => ['required', 'string', 'max:24'],
            'cost' => ['required', 'numeric', 'min:0'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    private function ownerOnly(): void
    {
        abort_unless(request()->user()?->isCentralOwner(), 403);
    }
}
