<div class="mb-3">
    <label class="form-label">Nama produk</label>
    <input name="name" class="form-control" value="{{ old('name', $product->name) }}" required maxlength="150" placeholder="Misal: Es Kopi Susu">
</div>
<div class="row g-3">
    <div class="col-6">
        <label class="form-label">SKU</label>
        <input name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" required maxlength="64" placeholder="P-001">
    </div>
    <div class="col-6">
        <label class="form-label">Harga jual (Rp)</label>
        <input name="selling_price" type="number" min="0" step="0.01" class="form-control" value="{{ old('selling_price', $product->selling_price ?? 0) }}" required>
    </div>
</div>
<div class="form-switch mb-3">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="prodActive" @checked(old('is_active', $product->exists ? $product->is_active : true))>
    <label class="form-check-label" for="prodActive">Aktif (muncul di kasir)</label>
</div>

<hr class="my-3">
<div class="d-flex justify-content-between align-items-center mb-2">
    <label class="form-label mb-0"><b>Komposisi resep</b> <span class="text-secondary small">(opsional, untuk mengurangi stok bahan)</span></label>
    <button type="button" class="btn btn-sm btn-outline-primary" id="addRecipe"><i class="fa-solid fa-plus me-1"></i>Tambah</button>
</div>
<div id="recipeWrap"></div>
<p class="text-secondary small mb-0"><i class="fa-solid fa-circle-info me-1"></i>Produk tanpa resep tetap bisa dijual, tetapi tidak mengurangi stok bahan.</p>

<script>
(() => {
    const ingredients = @json($ingredients->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit])->values());
    const existing = @json($product->recipes->map(fn ($r) => ['ingredient_id' => $r->ingredient_id, 'quantity' => (float) $r->quantity])->values());
    const wrap = document.getElementById('recipeWrap');
    const options = ingredients.map(i => `<option value="${i.id}">${i.name} (${i.unit})</option>`).join('');
    let idx = 0;

    function addRow(data = null) {
        const i = idx++;
        wrap.insertAdjacentHTML('beforeend', `
            <div class="row g-2 mb-2 recipe-row align-items-center">
                <div class="col-7">
                    <select name="recipes[${i}][ingredient_id]" class="form-select">
                        <option value="">Pilih bahan…</option>${options}
                    </select>
                </div>
                <div class="col-3">
                    <input name="recipes[${i}][quantity]" class="form-control" type="number" min="0.001" step="0.001" placeholder="Jumlah">
                </div>
                <div class="col-2 d-grid">
                    <button type="button" class="btn btn-outline-danger btn-sm" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            </div>`);
        const row = wrap.lastElementChild;
        if (data) {
            row.querySelector('select').value = data.ingredient_id;
            row.querySelector('input').value = data.quantity;
        }
    }
    wrap.addEventListener('click', e => {
        if (e.target.closest('.btn')) e.target.closest('.recipe-row').remove();
    });
    document.getElementById('addRecipe').addEventListener('click', () => addRow());
    existing.forEach(addRow);
})();
</script>
