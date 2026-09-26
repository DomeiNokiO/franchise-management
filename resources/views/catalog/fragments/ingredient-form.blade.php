<div class="mb-3">
    <label class="form-label">Nama bahan</label>
    <input name="name" class="form-control" value="{{ old('name', $ingredient->name) }}" required maxlength="150" placeholder="Misal: Tepung Terigu">
</div>
<div class="row g-3">
    <div class="col-6">
        <label class="form-label">SKU</label>
        <input name="sku" class="form-control" value="{{ old('sku', $ingredient->sku) }}" required maxlength="64" placeholder="T-001">
    </div>
    <div class="col-6">
        <label class="form-label">Satuan</label>
        <input name="unit" class="form-control" value="{{ old('unit', $ingredient->unit) }}" required maxlength="24" placeholder="kg, gram, liter, pcs">
    </div>
    <div class="col-6">
        <label class="form-label">Harga pokok (Rp)</label>
        <input name="cost" type="number" min="0" step="0.01" class="form-control" value="{{ old('cost', $ingredient->cost ?? 0) }}" required>
    </div>
    <div class="col-6">
        <label class="form-label">Reorder point</label>
        <input name="reorder_point" type="number" min="0" step="0.001" class="form-control" value="{{ old('reorder_point', $ingredient->reorder_point ?? 0) }}" required>
    </div>
</div>
<div class="form-switch mt-3">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ingActive" @checked(old('is_active', $ingredient->exists ? $ingredient->is_active : true))>
    <label class="form-check-label" for="ingActive">Aktif (bisa dipakai di resep, PO, dan stok)</label>
</div>
