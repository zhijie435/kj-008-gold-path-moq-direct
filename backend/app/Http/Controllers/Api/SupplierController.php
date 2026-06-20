<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Moq\MoqDirectShipException;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\MoqDirectShipService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(protected MoqDirectShipService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-suppliers');

        $suppliers = $this->service->getSupplierList([
            'keyword' => $request->input('keyword'),
            'is_active' => $request->input('is_active'),
            'province' => $request->input('province'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return $this->respondPaginated($suppliers);
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view-suppliers');

        return $this->respond(
            $supplier->loadCount(['products', 'orders'])->load('products')
        );
    }

    public function store(Request $request)
    {
        $this->authorize('manage-suppliers');

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
            'created_by' => $request->user()?->id,
        ]));

        return $this->respondCreated($supplier, '供应商创建成功');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('manage-suppliers');

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
            'updated_by' => $request->user()?->id,
        ]));

        return $this->respond($supplier, '供应商更新成功');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete-suppliers');

        if ($supplier->products()->exists()) {
            throw new MoqDirectShipException('该供应商下存在商品，无法删除');
        }

        $supplier->delete();

        return $this->respond(null, '供应商删除成功');
    }

    public function toggleActive(Supplier $supplier)
    {
        $this->authorize('manage-suppliers');

        $supplier->update([
            'is_active' => !$supplier->is_active,
            'updated_by' => request()->user()?->id,
        ]);

        return $this->respond($supplier, '状态切换成功');
    }

    public function getStatusOptions()
    {
        $this->authorize('view-suppliers');

        return $this->respond(Supplier::getStatusOptions());
    }

    public function allActive()
    {
        $this->authorize('view-suppliers');

        $suppliers = Supplier::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code', 'contact_person', 'phone']);

        return $this->respond($suppliers);
    }
}
