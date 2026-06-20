<template>
  <div class="product-list">
    <div class="toolbar">
      <div class="toolbar-left">
        <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>新建产品
      </el-button>
      </div>
    </div>

    <el-card class="filter-card" shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="产品名称/SKU/条码"
            clearable
            style="width: 240px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="供应商">
          <el-select v-model="queryParams.supplier_id" placeholder="全部" clearable style="width: 180px">
            <el-option
              v-for="item in supplierOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="queryParams.category" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in categoryList"
              :key="item"
              :label="item"
              :value="item"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.is_active" placeholder="全部" clearable style="width: 120px">
            <el-option label="上架" :value="true" />
            <el-option label="下架" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item label="低库存">
          <el-switch v-model="queryParams.is_low_stock" active-text="是" inactive-text="否" />
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

    <el-card class="table-card" shadow="never">
      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        style="width: 100%"
      >
        <el-table-column prop="name" label="产品名称" min-width="180">
          <template #default="{ row }">
            <div class="product-name">
              <div class="product-thumb">
                <img v-if="row.images && row.images[0]" :src="row.images[0]" alt="" />
                <el-icon v-else class="default-icon"><Goods /></el-icon>
              </div>
              <div class="product-info">
                <div class="name">{{ row.name }}</div>
                <div class="sku">SKU: {{ row.sku }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="supplier.name" label="供应商" width="140">
          <template #default="{ row }">
            {{ row.supplier?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="100" />
        <el-table-column prop="specification" label="规格" width="140" />
        <el-table-column prop="moq" label="MOQ起订" width="100" align="right">
          <template #default="{ row }">
            {{ row.moq }}{{ row.unit }}
          </template>
        </el-table-column>
        <el-table-column prop="price" label="售价" width="100" align="right">
          <template #default="{ row }">
            <span class="price">¥{{ formatMoney(row.price) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="stock_quantity" label="库存" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.stock_quantity <= row.safety_stock ? 'danger' : 'success'" size="small">
              {{ row.stock_quantity }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_active" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggleStatus(row)"
              active-text="上"
              inactive-text="下"
              inline-prompt
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button type="warning" link size="small" @click="handleStock(row)">
              库存
            </el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">
              删除
            </el-button>
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
      :title="dialogTitle"
      width="720px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="产品名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入产品名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="SKU编码" prop="sku">
              <el-input v-model="formData.sku" placeholder="请输入SKU编码" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="供应商" prop="supplier_id">
              <el-select v-model="formData.supplier_id" placeholder="请选择供应商" style="width: 100%">
                <el-option
                  v-for="item in supplierOptions"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="产品分类" prop="category">
              <el-input v-model="formData.category" placeholder="请输入分类" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="品牌" prop="brand">
              <el-input v-model="formData.brand" placeholder="请输入品牌" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规格型号" prop="specification">
              <el-input v-model="formData.specification" placeholder="请输入规格" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="计量单位" prop="unit">
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
          <el-col :span="8">
            <el-form-item label="MOQ起订" prop="moq">
              <el-input-number v-model="formData.moq" :min="1" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="安全库存" prop="safety_stock">
              <el-input-number v-model="formData.safety_stock" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="销售价格" prop="price">
              <el-input-number v-model="formData.price" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="成本价格" prop="cost_price">
              <el-input-number v-model="formData.cost_price" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="当前库存" prop="stock_quantity">
              <el-input-number v-model="formData.stock_quantity" :min="0" style="width: 100%" />
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
              <el-input v-model="formData.origin" placeholder="产地" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="产品描述" prop="description">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="3"
            placeholder="请输入产品描述"
          />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="是否启用" prop="is_active">
              <el-switch v-model="formData.is_active" active-text="是" inactive-text="否" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序" prop="sort_order">
              <el-input-number v-model="formData.sort_order" :min="0" style="width: 200px" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="stockDialogVisible" title="库存调整" width="500px">
      <el-form label-width="100px">
        <el-form-item label="当前库存">
          <span style="color: #409eff; font-weight: 600; font-size: 16px;">
        {{ currentProduct?.stock_quantity }}
      </span>
      </el-form-item>
      <el-form-item label="调整类型">
        <el-radio-group v-model="stockForm.type">
          <el-radio value="in">入库</el-radio>
          <el-radio value="out">出库</el-radio>
          <el-radio value="adjust">调整</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="数量">
        <el-input-number v-model="stockForm.quantity" :min="1" style="width: 100%" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="stockForm.remark" type="textarea" :rows="2" />
      </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="stockDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleStockSubmit">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Goods } from '@element-plus/icons-vue'
import {
  getProducts,
  createProduct,
  updateProduct,
  deleteProduct,
  toggleProductStatus,
  updateProductStock,
  getProductStatusOptions,
  getProductUnitOptions,
  getProductCategories,
  getAllSuppliers
} from '@/api/moqDirectShip'

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEdit = ref(false)
const formRef = ref(null)
const supplierOptions = ref([])
const unitOptions = ref([])
const categoryList = ref([])

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  supplier_id: '',
  category: '',
  is_active: '',
  is_low_stock: false
})

const formData = reactive({
  id: null,
  name: '',
  sku: '',
  barcode: '',
  supplier_id: '',
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
  images: [],
  attributes: {},
  stock_quantity: 0,
  safety_stock: 0,
  is_active: true,
  sort_order: 0
})

const formRules = {
  name: [{ required: true, message: '请输入产品名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入SKU编码', trigger: 'blur' }],
  supplier_id: [{ required: true, message: '请选择供应商', trigger: 'change' }],
  moq: [{ required: true, message: '请输入MOQ起订量', trigger: 'blur' }],
  price: [{ required: true, message: '请输入销售价格', trigger: 'blur' }]
}

const stockDialogVisible = ref(false)
const currentProduct = ref(null)
const stockForm = reactive({
  type: 'in',
  quantity: 1,
  remark: ''
})

function formatMoney(value) {
  if (!value && value !== 0) return '0.00'
  return Number(value).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

async function fetchSupplierOptions() {
  try {
    const res = await getAllSuppliers()
    if (res.code === 0) {
      supplierOptions.value = res.data
    }
  } catch (error) {
    console.error('获取供应商列表失败:', error)
  }
}

async function fetchUnitOptions() {
  try {
    const res = await getProductUnitOptions()
    if (res.code === 0) {
      unitOptions.value = res.data
    }
  } catch (error) {
    console.error('获取单位选项失败:', error)
  }
}

async function fetchCategories() {
  try {
    const res = await getProductCategories()
    if (res.code === 0) {
      categoryList.value = res.data
    }
  } catch (error) {
    console.error('获取分类列表失败:', error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (params.is_active === '') {
      delete params.is_active
    }

    const res = await getProducts(params)
    if (res.code === 0) {
      tableData.value = res.data.list
      total.value = res.data.total
    }
  } catch (error) {
    console.error('获取产品列表失败:', error)
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  queryParams.page = 1
  fetchData()
}

function handleReset() {
  queryParams.keyword = ''
  queryParams.supplier_id = ''
  queryParams.category = ''
  queryParams.is_active = ''
  queryParams.is_low_stock = false
  queryParams.page = 1
  fetchData()
}

function handleAdd() {
  isEdit.value = false
  dialogTitle.value = '新建产品'
  Object.assign(formData, {
    id: null,
    name: '',
    sku: '',
    barcode: '',
    supplier_id: '',
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
    images: [],
    attributes: {},
    stock_quantity: 0,
    safety_stock: 0,
    is_active: true,
    sort_order: 0
  })
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  dialogTitle.value = '编辑产品'
  Object.assign(formData, { ...row })
  dialogVisible.value = true
}

async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value) {
      const res = await updateProduct(formData.id, formData)
      if (res.code === 0) {
        ElMessage.success('更新成功')
        dialogVisible.value = false
        fetchData()
      }
    } else {
      const res = await createProduct(formData)
      if (res.code === 0) {
        ElMessage.success('创建成功')
        dialogVisible.value = false
        fetchData()
      }
    }
  } catch (error) {
    console.error('提交失败:', error)
  } finally {
    submitLoading.value = false
  }
}

async function handleToggleStatus(row) {
  try {
    const res = await toggleProductStatus(row.id)
    if (res.code === 0) {
      ElMessage.success('操作成功')
    }
  } catch (error) {
    console.error('切换状态失败:', error)
    row.is_active = !row.is_active
  }
}

function handleStock(row) {
  currentProduct.value = row
  stockForm.type = 'in'
  stockForm.quantity = 1
  stockForm.remark = ''
  stockDialogVisible.value = true
}

async function handleStockSubmit() {
  try {
    const res = await updateProductStock(currentProduct.value.id, stockForm)
    if (res.code === 0) {
      ElMessage.success('库存调整成功')
      stockDialogVisible.value = false
      fetchData()
    }
  } catch (error) {
    console.error('库存调整失败:', error)
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      `确定要删除产品 "${row.name}" 吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const res = await deleteProduct(row.id)
    if (res.code === 0) {
      ElMessage.success('删除成功')
      fetchData()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
    }
  }
}

onMounted(() => {
  fetchSupplierOptions()
  fetchUnitOptions()
  fetchCategories()
  fetchData()
})
</script>

<style scoped lang="scss">
.product-list {
  padding: 20px 0;

  .toolbar {
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
  }

  .filter-card {
    margin-bottom: 20px;
  }

  .product-name {
    display: flex;
    align-items: center;
    gap: 12px;

    .product-thumb {
      width: 48px;
      height: 48px;
      border-radius: 6px;
      overflow: hidden;
      background: #f5f7fa;
      display: flex;
      align-items: center;
      justify-content: center;

      img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .default-icon {
        font-size: 24px;
        color: #c0c4cc;
      }
    }

    .product-info {
      .name {
        font-weight: 500;
        color: #303133;
        margin-bottom: 4px;
      }

      .sku {
        font-size: 12px;
        color: #909399;
      }
    }
  }

  .price {
    color: #f56c6c;
    font-weight: 600;
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }
}
</style>
