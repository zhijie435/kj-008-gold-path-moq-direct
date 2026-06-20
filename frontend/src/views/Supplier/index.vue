<template>
  <div class="supplier-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">供应商管理</h2>
        <p class="page-desc">管理国内 MOQ 直发供应商信息</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建供应商
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="名称/编码/联系人/电话"
            clearable
            style="width: 220px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.is_active" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="true" />
            <el-option label="禁用" :value="false" />
          </el-select>
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
        <el-table-column prop="code" label="编码" width="120" />
        <el-table-column prop="name" label="供应商名称" min-width="180" />
        <el-table-column label="联系人" width="120">
          <template #default="{ row }">{{ row.contact_person || '-' }}</template>
        </el-table-column>
        <el-table-column label="联系电话" width="140">
          <template #default="{ row }">{{ row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="地址" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ row.full_address || '-' }}</template>
        </el-table-column>
        <el-table-column label="商品数量" width="100" align="center" prop="products_count" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggle(row)"
              active-text="启用"
              inactive-text="禁用"
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">查看</el-button>
            <el-button type="primary" link size="small" @click="handleEdit(row)">编辑</el-button>
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
      :title="isEdit ? '编辑供应商' : '新建供应商'"
      width="720px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="供应商名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入供应商名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="编码" prop="code">
              <el-input v-model="formData.code" placeholder="请输入编码" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="联系人" prop="contact_person">
              <el-input v-model="formData.contact_person" placeholder="请输入联系人" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="phone">
              <el-input v-model="formData.phone" placeholder="请输入联系电话" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="formData.email" placeholder="请输入邮箱" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税号" prop="tax_number">
              <el-input v-model="formData.tax_number" placeholder="请输入税号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="省份" prop="province">
              <el-input v-model="formData.province" placeholder="省份" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="城市" prop="city">
              <el-input v-model="formData.city" placeholder="城市" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="区县" prop="district">
              <el-input v-model="formData.district" placeholder="区县" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="详细地址" prop="address">
          <el-input v-model="formData.address" placeholder="请输入详细地址" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="营业执照号" prop="business_license">
              <el-input v-model="formData.business_license" placeholder="请输入营业执照号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序" prop="sort_order">
              <el-input-number v-model="formData.sort_order" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开户银行" prop="bank_name">
              <el-input v-model="formData.bank_name" placeholder="请输入开户银行" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="银行账号" prop="bank_account">
              <el-input v-model="formData.bank_account" placeholder="请输入银行账号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="是否启用" prop="is_active">
          <el-switch v-model="formData.is_active" active-text="启用" inactive-text="禁用" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" :rows="3" placeholder="备注信息" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="供应商详情" size="500px">
      <div v-if="currentSupplier" class="supplier-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="编码">{{ currentSupplier.code }}</el-descriptions-item>
          <el-descriptions-item label="名称">{{ currentSupplier.name }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ currentSupplier.contact_person || '-' }}</el-descriptions-item>
          <el-descriptions-item label="电话">{{ currentSupplier.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ currentSupplier.email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="税号">{{ currentSupplier.tax_number || '-' }}</el-descriptions-item>
          <el-descriptions-item label="地址" :span="2">
            {{ currentSupplier.full_address || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="开户银行">{{ currentSupplier.bank_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="银行账号">{{ currentSupplier.bank_account || '-' }}</el-descriptions-item>
          <el-descriptions-item label="营业执照">{{ currentSupplier.business_license || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="currentSupplier.is_active ? 'success' : 'info'">
              {{ currentSupplier.is_active ? '启用' : '禁用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">
            {{ currentSupplier.remark || '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left" v-if="currentSupplier.products?.length">商品列表</el-divider>
        <el-table v-if="currentSupplier.products?.length" :data="currentSupplier.products" border size="small">
          <el-table-column prop="sku" label="SKU" width="120" />
          <el-table-column prop="name" label="商品名称" min-width="160" />
          <el-table-column label="价格" width="100" align="right">
            <template #default="{ row }">¥{{ Number(row.price || 0).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column prop="stock_quantity" label="库存" width="80" align="center" />
        </el-table>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getSuppliers,
  getSupplier,
  createSupplier,
  updateSupplier,
  deleteSupplier,
  toggleSupplierActive
} from '@/api/supplier'

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formRef = ref(null)
const detailVisible = ref(false)
const currentSupplier = ref(null)

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  is_active: ''
})

const formData = reactive({
  id: null,
  name: '',
  code: '',
  contact_person: '',
  phone: '',
  email: '',
  province: '',
  city: '',
  district: '',
  address: '',
  business_license: '',
  bank_name: '',
  bank_account: '',
  tax_number: '',
  remark: '',
  is_active: true,
  sort_order: 0
})

const formRules = {
  name: [{ required: true, message: '请输入供应商名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入编码', trigger: 'blur' }]
}

function resetForm() {
  Object.assign(formData, {
    id: null,
    name: '',
    code: '',
    contact_person: '',
    phone: '',
    email: '',
    province: '',
    city: '',
    district: '',
    address: '',
    business_license: '',
    bank_name: '',
    bank_account: '',
    tax_number: '',
    remark: '',
    is_active: true,
    sort_order: 0
  })
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (params.is_active === '') delete params.is_active
    const res = await getSuppliers(params)
    tableData.value = res.data
    total.value = res.total
  } catch (e) {
    console.error(e)
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
  queryParams.is_active = ''
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
    code: row.code,
    contact_person: row.contact_person || '',
    phone: row.phone || '',
    email: row.email || '',
    province: row.province || '',
    city: row.city || '',
    district: row.district || '',
    address: row.address || '',
    business_license: row.business_license || '',
    bank_name: row.bank_name || '',
    bank_account: row.bank_account || '',
    tax_number: row.tax_number || '',
    remark: row.remark || '',
    is_active: row.is_active,
    sort_order: row.sort_order || 0
  })
  dialogVisible.value = true
}

async function handleView(row) {
  try {
    const res = await getSupplier(row.id)
    currentSupplier.value = res.data
    detailVisible.value = true
  } catch (e) {
    console.error(e)
  }
}

async function handleToggle(row) {
  try {
    await toggleSupplierActive(row.id)
    ElMessage.success(row.is_active ? '已启用' : '已禁用')
  } catch (e) {
    row.is_active = !row.is_active
    console.error(e)
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定要删除供应商"${row.name}"吗？`, '删除确认', {
      type: 'warning',
      confirmButtonClass: 'el-button--danger'
    })
    await deleteSupplier(row.id)
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
      await updateSupplier(formData.id, formData)
      ElMessage.success('更新成功')
    } else {
      await createSupplier(formData)
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
  fetchData()
})
</script>

<style scoped lang="scss">
.supplier-page {
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;
    .page-title { font-size: 20px; color: #303133; margin: 0 0 4px 0; }
    .page-desc { font-size: 13px; color: #909399; margin: 0; }
  }
  .filter-card { margin-bottom: 20px; }
  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }
}
</style>
