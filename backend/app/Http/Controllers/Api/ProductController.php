<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('supplier');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('stock_quantity <= safety_stock');
        }

        $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');

        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'list' => $products->items(),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
            ],
        ]);
    }

    public function show(Product $product)
    {
        $product->load('supplier');
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'sku' => 'required|string|max:50|unique:products,sku',
            'barcode' => 'nullable|string|max:50',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:20',
            'moq' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'origin' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'attributes' => 'nullable|array',
            'stock_quantity' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $product = Product::create(array_merge($validated, [
            'created_by' => auth()->id(),
        ]));

        $product->load('supplier');

        return response()->json([
            'code' => 0,
            'message' => '商品创建成功',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'sku' => 'sometimes|string|max:50|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:50',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:200',
            'unit' => 'nullable|string|max:20',
            'moq' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'origin' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'attributes' => 'nullable|array',
            'stock_quantity' => 'nullable|integer|min:0',
            'safety_stock' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $product->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        $product->load('supplier');

        return response()->json([
            'code' => 0,
            'message' => '商品更新成功',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'code' => 0,
            'message' => '商品删除成功',
            'data' => null,
        ]);
    }

    public function toggleActive(Product $product)
    {
        $product->update([
            'is_active' => !$product->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '状态切换成功',
            'data' => $product,
        ]);
    }

    public function getUnitOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => Product::getUnitOptions(),
        ]);
    }

    public function getStatusOptions()
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => Product::getStatusOptions(),
        ]);
    }

    public function getCategories()
    {
        $categories = Product::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->values()
            ->toArray();

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $categories,
        ]);
    }

    public function updateStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out,adjust',
            'remark' => 'nullable|string',
        ]);

        $quantity = $validated['quantity'];
        $type = $validated['type'];

        $newStock = match ($type) {
            'in' => $product->stock_quantity + $quantity,
            'out' => max(0, $product->stock_quantity - $quantity),
            'adjust' => $quantity,
        };

        $product->update([
            'stock_quantity' => $newStock,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '库存更新成功',
            'data' => $product,
        ]);
    }
}
