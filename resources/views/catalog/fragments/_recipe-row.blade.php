<div class="row g-2 mb-2 recipe-row align-items-center">
    <div class="col-8">
        <select name="recipes[{{ $loop->parent?->index ?? 0 }}][ingredient_id]" class="form-select">
            <option value="">Pilih bahan…</option>
            @foreach($ingredients as $ingredient)
            <option value="{{ $ingredient->id }}" @selected((int) ($ingredient_id ?? 0) === $ingredient->id)>{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-3">
        <input name="recipes[{{ $loop->parent?->index ?? 0 }}][quantity]" class="form-control" type="number" min="0.001" step="0.001" placeholder="Jumlah" value="{{ $quantity ?? '' }}">
    </div>
    <div class="col-1 d-grid">
        <button type="button" class="btn btn-outline-danger btn-sm" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
    </div>
</div>
