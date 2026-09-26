<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Helper standar DataTables (serverside processing) untuk Eloquent.
 * Mendukung: draw, start, length, search[value], order[0][column]/[dir].
 *
 * $scope menerima (query, search) dan boleh menambahkan filter apa pun
 * (mis. isolasi tenant). Filter tetap berlaku untuk count & data;
 * search diterapkan hanya untuk recordsFiltered.
 */
final class DataTables
{
    /**
     * @param array<int, string> $sortable kolom DB yang boleh diurutkan (index = index kolom tabel)
     * @param Closure $scope fn (Builder $query, string $search): Builder
     * @param Closure $row   fn (Model $m): array
     */
    public static function process(Builder $query, array $sortable, Closure $scope, Closure $row): JsonResponse
    {
        $request = app('request');
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length === -1 ? 100 : min($length, 100);
        $search = trim((string) $request->input('search.value', ''));

        // recordsTotal: filter tetap saja (scope dengan search kosong, pada salinan query)
        $recordsTotal = (clone $scope(clone $query, ''))->count();

        // Terapkan scope penuh (filter tetap + search)
        $query = $scope($query, $search);
        $recordsFiltered = (clone $query)->count();

        // Sorting
        $orderIndex = (int) $request->input('order[0][column]', 0);
        $dir = strtolower((string) $request->input('order[0][dir]', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (isset($sortable[$orderIndex])) {
            $query->orderBy($sortable[$orderIndex], $dir);
        }

        $items = $query->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $items->map($row)->values()->all(),
        ]);
    }
}
