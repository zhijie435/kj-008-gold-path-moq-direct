<template>
  <div class="product-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">商品管理</h2>
        <p class="page-desc">管理国内 MOQ 直发商品库存与信息</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建商品
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="名称/SKU/条码"
            clearable
            style="width: 220px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="供应商">
          <el-select v-model="queryParams.supplier_id" placeholder="全部" clearable filterable style="width: 160px">
            <el-option
              v-for="item in supplierList"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.is_active" placeholder="全部" clearable style="width: 120px">
            <el-option label="上架" :value="true" />
            <el-option label="下架" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item label="库存预警">
          <el-switch v-model="queryParams.low_stock" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <el-icon><Search /></el-icon>搜索
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon>重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" stripe border style="width: 100%">
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column label="商品信息" min-width="220">
          <template #default="{ row }">
            <div class="product-info">
              <div class="product-name">{{ row.name }}</div>
              <div class="product-sku">
                <el-tag size="small" type="info">{{ row.sku }}</el-tag>
                <span v-if="row.barcode" class="barcode">{{ row.barcode }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="分类" width="100" prop="category">
          <template #default="{ row }">{{ row.category || '-' }}</template>
        </el-table-column>
        <el-table-column label="品牌" width="100" prop="brand">
          <template #default="{ row }">{{ row.brand || '-' }}</template>
        </el-table-column>
        <el-table-column label="规格" width="120" prop="specification">
          <template #default="{ row }">{{ row.specification || '-' }}</template>
        </el-table-column>
        <el-table-column label="供应商" width="140">
          <template #default="{ row }">{{ row.supplier?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="MOQ" width="80" align="center" prop="moq" />
        <el-table-column label="价格" width="120" align="right">
          <template #default="{ row }">
            <div>
              <div class="price">¥{{ formatAmount(row.price) }}</div>
              <div class="cost-price" style="font-size: 12px; color: #909399">成本 ¥{{ formatAmount(row.cost_price) }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="库存" width="140" align="center">
          <template #default="{ row }">
            <el-tag
              :type="row.is_low_stock ? 'danger' : (row.stock_quantity > 0 ? 'success' : 'info')"
              size="small"
            >
              {{ row.stock_quantity }}
            </el-tag>
            <el-tooltip v-if="row.is_low_stock" content="库存低于安全库存" placement="top">
              <el-icon class="warning-icon"><Warning /></el-icon>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggle(row)"
              active-text="上架"
              inactive-text="下架"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button type="warning" link size="small" @click="handleStock(row)">库存</el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.per_page"
          :page-sizes="[10, 15, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="fetchData"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <el-dialog
      v-model="dialogVisible"
      :title="isEdit ? '编辑商品' : '新建商品'"
      width="800px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="商品名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入商品名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="SKU编码" prop="sku">
              <el-input v-model="formData.sku" placeholder="请输入SKU" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="条码" prop="barcode">
              <el-input v-model="formData.barcode" placeholder="选填" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="分类" prop="category">
              <el-input v-model="formData.category" placeholder="选填" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="品牌" prop="brand">
              <el-input v-model="formData.brand" placeholder="选填" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="供应商" prop="supplier_id">
              <el-select v-model="formData.supplier_id" placeholder="请选择" filterable clearable style="width: 100%">
                <el-option
                  v-for="item in supplierList"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="规格" prop="specification">
              <el-input v-model="formData.specification" placeholder="选填" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="单位" prop="unit">
              <el-select v-model="formData.unit" placeholder="请选择" style="width: 100%">
                <el-option
                  v-for="item in unitOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="MOQ起订量" prop="moq">
              <el-input-number v-model="formData.moq" :min="1" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="销售价(¥)" prop="price">
              <el-input-number v-model="formData.price" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="成本价(¥)" prop="cost_price">
              <el-input-number v-model="formData.cost_price" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="重量(kg)" prop="weight">
              <el-input-number v-model="formData.weight" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="体积(m³)" prop="volume">
              <el-input-number v-model="formData.volume" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="产地" prop="origin">
              <el-input v-model="formData.origin" placeholder="选填" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="库存数量" prop="stock_quantity">
              <el-input-number v-model="formData.stock_quantity" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="安全库存" prop="safety_stock">
              <el-input-number v-model="formData.safety_stock" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="排序" prop="sort_order">
              <el-input-number v-model="formData.sort_order" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="是否上架" prop="is_active">
          <el-switch v-model="formData.is_active" active-text="上架" inactive-text="下架" />
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="商品描述" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="stockDialogVisible" title="库存调整" width="480px">
      <el-form ref="stockFormRef" :model="stockForm" :rules="stockFormRules" label-width="100px">
        <el-form-item label="商品">
          <span>{{ currentProduct?.name }}</span>
        </el-form-item>
        <el-form-item label="当前库存">
          <el-tag type="info">{{ currentProduct?.stock_quantity }}</el-tag>
        </el-form-item>
        <el-form-item label="调整方式" prop="type">
          <el-radio-group v-model="stockForm.type">
            <el-radio value="in">入库</el-radio>
            <el-radio value="out">出库</el-radio>
            <el-radio value="adjust">盘点</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="调整数量" prop="quantity">
          <el-input-number v-model="stockForm.quantity" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="stockForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="stockDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleStockSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getProducts,
  createProduct,
  updateProduct,
  deleteProduct,
  toggleProductActive,
  getProductUnitOptions,
  updateProductStock
} from '@/api/product'
import { getActiveSuppliers } from '@/api/supplier'

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const supplierList = ref([])
const unitOptions = ref([])
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const stockDialogVisible = ref(false)
const stockFormRef = ref(null)
const currentProduct = ref(null)

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  supplier_id: '',
  is_active: '',
  low_stock: false
})

const formData = reactive({
  id: null,
  name: '',
  sku: '',
  barcode: '',
  supplier_id: null,
  category: '',
  brand: '',
  specification: '',
  unit: '件',
  moq: 1,
  price: 0,
  cost_price: 0,
  weight: 0,
  volume: 0,
  origin: '',
  description: '',
  stock_quantity: 0,
  safety_stock: 0,
  is_active: true,
  sort_order: 0
})

const formRules = {
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入SKU编码', trigger: 'blur' }]
}

const stockForm = reactive({
  quantity: 0,
  type: 'in',
  remark: ''
})

const stockFormRules = {
  quantity: [{ required: true, message: '请输入数量', trigger: 'blur' }],
  type: [{ required: true, message: '请选择调整方式', trigger: 'change' }]
}

function formatAmount(val) {
  return Number(val || 0).toFixed(2)
}

function resetForm() {
  Object.assign(formData, {
    id: null,
    name: '',
    sku: '',
    barcode: '',
    supplier_id: null,
    category: '',
    brand: '',
    specification: '',
    unit: '件',
    moq: 1,
    price: 0,
    cost_price: 0,
    weight: 0,
    volume: 0,
    origin: '',
    description: '',
    stock_quantity: 0,
    safety_stock: 0,
    is_active: true,
    sort_order: 0
  })
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (params.is_active === '') delete params.is_active
    if (!params.low_stock) delete params.low_stock
    const res = await getProducts(params)
    tableData.value = res.data
    total.value = res.total
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchOptions() {
  try {
    const [supplierRes, unitRes] = await Promise.all([
      getActiveSuppliers(),
      getProductUnitOptions()
    ])
    supplierList.value = supplierRes.data
    unitOptions.value = unitRes.data
  } catch (e) {
    console.error(e)
  }
}

function handleSearch() {
  queryParams.page = 1
  fetchData()
}

function handleReset() {
  queryParams.keyword = ''
  queryParams.supplier_id = ''
  queryParams.is_active = ''
  queryParams.low_stock = false
  queryParams.page = 1
  fetchData()
}

function handleAdd() {
  isEdit.value = false
  resetForm()
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  Object.assign(formData, {
    id: row.id,
    name: row.name,
    sku: row.sku,
    barcode: row.barcode || '',
    supplier_id: row.supplier_id,
    category: row.category || '',
    brand: row.brand || '',
    specification: row.specification || '',
    unit: row.unit || '件',
    moq: row.moq || 1,
    price: row.price || 0,
    cost_price: row.cost_price || 0,
    weight: row.weight || 0,
    volume: row.volume || 0,
    origin: row.origin || '',
    description: row.description || '',
    stock_quantity: row.stock_quantity || 0,
    safety_stock: row.safety_stock || 0,
    is_active: row.is_active,
    sort_order: row.sort_order || 0
  })
  dialogVisible.value = true
}

async function handleToggle(row) {
  try {
    await toggleProductActive(row.id)
    ElMessage.success(row.is_active ? '已上架' : '已下架')
  } catch (e) {
    row.is_active = !row.is_active
    console.error(e)
  }
}

function handleStock(row) {
  currentProduct.value = row
  Object.assign(stockForm, { quantity: 0, type: 'in', remark: '' })
  stockDialogVisible.value = true
}

async function handleStockSubmit() {
  if (!stockFormRef.value) return
  try {
    await stockFormRef.value.validate()
    await updateProductStock(currentProduct.value.id, stockForm)
    ElMessage.success('库存调整成功')
    stockDialogVisible.value = false
    fetchData()
  } catch (e) {
    console.error(e)
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定要删除商品"${row.name}"吗？`, '删除确认', {
      type: 'warning',
      confirmButtonClass: 'el-button--danger'
    })
    await deleteProduct(row.id)
    ElMessage.success('删除成功')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value) {
      await updateProduct(formData.id, formData)
      ElMessage.success('更新成功')
    } else {
      await createProduct(formData)
      ElMessage.success('创建成功')
    }

    dialogVisible.value = false
    fetchData()
  } catch (e) {
    console.error(e)
  } finally {
    submitLoading.value = false
  }
}

onMounted(() => {
  fetchOptions()
  fetchData()
})
</script>

<style scoped lang="scss">
.product-page {
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;
    .page-title { font-size: 20px; color: #303133; margin: 0 0 4px 0; }
    .page-desc { font-size: 13px; color: #909399; margin: 0; }
  }
  .filter-card { margin-bottom: 20px; }
  .table-card {
    .product-info {
      .product-name { font-weight: 500; color: #303133; }
      .product-sku {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        .barcode { font-size: 12px; color: #909399; }
      }
    }
    .price { color: #f56c6c; font-weight: 600; }
    .warning-icon { color: #e6a23c; margin-left: 4px; }
  }
  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }
}
</style>
