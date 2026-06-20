<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Moq\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\MoqDirectShipService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected MoqDirectShipService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-products');

        $products = $this->service->getProductList([
            'keyword' => $request->input('keyword'),
            'supplier_id' => $request->input('supplier_id'),
            'category' => $request->input('category'),
            'is_active' => $request->input('is_active'),
            'is_low_stock' => $request->boolean('low_stock') || $request->boolean('is_low_stock') ? 1 : null,
            'moq_min' => $request->input('moq_min'),
            'moq_max' => $request->input('moq_max'),
            'page' => $request->input('page'),
            'per_page' => $request->input('per_page', 15),
        ]);

        return $this->respondPaginated($products);
    }

    public function show(Product $product)
    {
        $this->authorize('view-products');

        return $this->respond($product->load('supplier'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-products');

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
            'created_by' => $request->user()?->id,
        ]));

        return $this->respondCreated($product->load('supplier'), '商品创建成功');
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('manage-products');

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
            'updated_by' => $request->user()?->id,
        ]));

        return $this->respond($product->load('supplier'), '商品更新成功');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete-products');

        $product->delete();

        return $this->respond(null, '商品删除成功');
    }

    public function toggleActive(Product $product)
    {
        $this->authorize('manage-products');

        $product->update([
            'is_active' => !$product->is_active,
            'updated_by' => request()->user()?->id,
        ]);

        return $this->respond($product, '状态切换成功');
    }

    public function getUnitOptions()
    {
        $this->authorize('view-products');

        return $this->respond(Product::getUnitOptions());
    }

    public function getStatusOptions()
    {
        $this->authorize('view-products');

        return $this->respond(Product::getStatusOptions());
    }

    public function getCategories()
    {
        $this->authorize('view-products');

        $categories = Product::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->values()
            ->toArray();

        return $this->respond($categories);
    }

    public function updateStock(Request $request, Product $product)
    {
        $this->authorize('manage-products');

        $validated = $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out,adjust',
            'remark' => 'nullable|string',
        ]);

        $quantity = $validated['quantity'];
        $type = $validated['type'];

        if ($type === 'out' && $quantity > $product->stock_quantity) {
            throw new InsufficientStockException(
                "产品 {$product->name} 库存不足，当前库存 {$product->stock_quantity} {$product->unit}"
            );
        }

        $newStock = match ($type) {
            'in' => $product->stock_quantity + $quantity,
            'out' => $product->stock_quantity - $quantity,
            'adjust' => $quantity,
        };

        $product->update([
            'stock_quantity' => $newStock,
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond($product, '库存更新成功');
    }
}
