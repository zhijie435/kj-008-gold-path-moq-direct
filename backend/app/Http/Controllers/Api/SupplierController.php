<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount('products');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('contact_person', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        if ($request->filled('province')) {
            $query->where('province', $request->input('province'));
        }

        $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');

        $perPage = $request->input('per_page', 15);
        $suppliers = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'list' => $suppliers->items(),
                'total' => $suppliers->total(),
                'current_page' => $suppliers->currentPage(),
                'per_page' => $suppliers->perPage(),
            ],
        ]);
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('products');
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $supplier,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50|unique:suppliers,code',
            'contact_person' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'province' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
            'district' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'business_license' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $supplier = Supplier::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        return response()->json([
            'code' => 0,
            'message' => '供应商创建成功',
            'data' => $supplier,
        ], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'code' => 'sometimes|string|max:50|unique:suppliers,code,' . $supplier->id,
            'contact_person' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'province' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:50',
            'district' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'business_license' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $supplier->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        return response()->json([
            'code' => 0,
            'message' => '供应商更新成功',
            'data' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->products()->exists()) {
            return response()->json([
                'code' => 422,
                'message' => '该供应商下存在商品，无法删除',
                'data' => null,
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'code' => 0,
            'message' => '供应商删除成功',
            'data' => null,
        ]);
    }

    public function toggleActive(Supplier $supplier)
    {
        $supplier->update([
            'is_active' => !$supplier->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '状态切换成功',
            'data' => $supplier,
        ]);
    }

    public function getStatusOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => Supplier::getStatusOptions(),
        ]);
    }

    public function allActive()
    {
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'contact_person', 'phone']);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $suppliers,
        ]);
    }
}
