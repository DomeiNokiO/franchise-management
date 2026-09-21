<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View { $products = Product::with('recipes.ingredient')->latest()->paginate(25); return view('catalog.products.index', compact('products')); }
    public function create(): View { $this->ownerOnly(); return view('catalog.products.form', ['product' => new Product, 'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get()]); }

    public function store(Request $request): RedirectResponse
    {
        $this->ownerOnly();
        $data = $this->validated($request);
        $recipes = $data['recipes'] ?? [];
        unset($data['recipes']);
        $product = DB::transaction(function () use ($data, $recipes) { $product = Product::create($data); $this->syncRecipes($product, $recipes); return $product; });
        return redirect()->route('products.index')->with('success', 'Produk dan resep berhasil disimpan.');
    }

    public function edit(Product $product): View { $this->ownerOnly(); return view('catalog.products.form', ['product' => $product->load('recipes'), 'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get()]); }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->ownerOnly();
        $data = $this->validated($request, $product); $recipes = $data['recipes'] ?? []; unset($data['recipes']);
        DB::transaction(function () use ($product, $data, $recipes) { $product->update($data); $this->syncRecipes($product, $recipes); });
        return redirect()->route('products.index')->with('success', 'Produk dan resep berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->ownerOnly();
        if ($product->saleItems()->exists()) return back()->withErrors(['product' => 'Produk sudah memiliki transaksi dan tidak boleh dihapus. Nonaktifkan saja.']);
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Produk dihapus.');
    }

    private function syncRecipes(Product $product, array $recipes): void
    {
        $rows = [];
        foreach ($recipes as $recipe) { if (!empty($recipe['ingredient_id']) && (float) ($recipe['quantity'] ?? 0) > 0) $rows[(int) $recipe['ingredient_id']] = ['quantity' => $recipe['quantity']]; }
        $product->recipes()->delete();
        foreach ($rows as $ingredientId => $values) {
            $product->recipes()->create(['ingredient_id' => $ingredientId, 'quantity' => $values['quantity']]);
        }
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product?->id)],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'recipes' => ['nullable', 'array'],
            'recipes.*.ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'recipes.*.quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    private function ownerOnly(): void { abort_unless(request()->user()?->isCentralOwner(), 403); }
}
