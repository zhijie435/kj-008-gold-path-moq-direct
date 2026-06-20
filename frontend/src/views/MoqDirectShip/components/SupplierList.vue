<template>
  <div class="supplier-list">
    <div class="toolbar">
      <div class="toolbar-left">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建供应商
        </el-button>
      </div>
    </div>

    <el-card class="filter-card" shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="供应商名称/编码/联系人"
            clearable
            style="width: 240px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="省份">
          <el-input v-model="queryParams.province" placeholder="省份" clearable style="width: 120px" />
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

    <el-card class="table-card" shadow="never">
      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        style="width: 100%"
      >
        <el-table-column prop="code" label="供应商编码" width="140" />
        <el-table-column prop="name" label="供应商名称" min-width="180" />
        <el-table-column prop="contact_person" label="联系人" width="120" />
        <el-table-column prop="phone" label="联系电话" width="140" />
        <el-table-column label="所在地区" width="180">
          <template #default="{ row }">
            {{ row.province }}{{ row.city }}{{ row.district }}
          </template>
        </el-table-column>
        <el-table-column prop="products_count" label="产品数" width="100" align="center">
          <template #default="{ row }">
            {{ row.products_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="orders_count" label="订单数" width="100" align="center">
          <template #default="{ row }">
            {{ row.orders_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="is_active" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggleStatus(row)"
              active-text="启"
              inactive-text="禁"
              inline-prompt
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">
              查看
            </el-button>
            <el-button type="primary" link size="small" @click="handleEdit(row)">
              编辑
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
            <el-form-item label="供应商名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入供应商名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="供应商编码" prop="code">
              <el-input v-model="formData.code" placeholder="请输入编码" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="联系人" prop="contact_person">
              <el-input v-model="formData.contact_person" placeholder="联系人姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="phone">
              <el-input v-model="formData.phone" placeholder="联系电话" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="formData.email" placeholder="电子邮箱" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="所在地区">
              <el-input v-model="formData.province" placeholder="省" style="width: 30%" />
              <el-input v-model="formData.city" placeholder="市" style="width: 30%; margin: 0 5%" />
              <el-input v-model="formData.district" placeholder="区" style="width: 30%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="详细地址" prop="address">
          <el-input v-model="formData.address" type="textarea" :rows="2" placeholder="详细地址" />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="营业执照">
              <el-input v-model="formData.business_license" placeholder="营业执照号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="税号">
              <el-input v-model="formData.tax_number" placeholder="税务登记号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开户银行">
              <el-input v-model="formData.bank_name" placeholder="开户银行名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="银行账号">
              <el-input v-model="formData.bank_account" placeholder="银行账号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="2" placeholder="备注信息" />
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

    <el-drawer v-model="detailVisible" title="供应商详情" size="500px">
      <div v-if="currentSupplier" class="supplier-detail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="供应商编码">{{ currentSupplier.code }}</el-descriptions-item>
          <el-descriptions-item label="供应商名称">{{ currentSupplier.name }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ currentSupplier.contact_person || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ currentSupplier.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ currentSupplier.email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="所在地区">
            {{ currentSupplier.province }}{{ currentSupplier.city }}{{ currentSupplier.district }}
          </el-descriptions-item>
          <el-descriptions-item label="详细地址">{{ currentSupplier.address || '-' }}</el-descriptions-item>
          <el-descriptions-item label="营业执照">{{ currentSupplier.business_license || '-' }}</el-descriptions-item>
          <el-descriptions-item label="税号">{{ currentSupplier.tax_number || '-' }}</el-descriptions-item>
          <el-descriptions-item label="开户银行">{{ currentSupplier.bank_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="银行账号">{{ currentSupplier.bank_account || '-' }}</el-descriptions-item>
          <el-descriptions-item label="产品数量">{{ currentSupplier.products_count || 0 }}</el-descriptions-item>
          <el-descriptions-item label="订单数量">{{ currentSupplier.orders_count || 0 }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="currentSupplier.is_active ? 'success' : 'info'">
              {{ currentSupplier.is_active ? '启用' : '禁用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="备注">{{ currentSupplier.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import {
  getSuppliers,
  getSupplier,
  createSupplier,
  updateSupplier,
  deleteSupplier,
  toggleSupplierStatus,
  getSupplierStatusOptions
} from '@/api/moqDirectShip'

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const dialogTitle = ref('')
const isEdit = ref(false)
const formRef = ref(null)
const detailVisible = ref(false)
const currentSupplier = ref(null)

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  province: '',
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
  code: [{ required: true, message: '请输入供应商编码', trigger: 'blur' }]
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (params.is_active === '') {
      delete params.is_active
    }

    const res = await getSuppliers(params)
    if (res.code === 0) {
      tableData.value = res.data.list
      total.value = res.data.total
    }
  } catch (error) {
    console.error('获取供应商列表失败:', error)
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
  queryParams.province = ''
  queryParams.is_active = ''
  queryParams.page = 1
  fetchData()
}

function handleAdd() {
  isEdit.value = false
  dialogTitle.value = '新建供应商'
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
  dialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  dialogTitle.value = '编辑供应商'
  Object.assign(formData, { ...row })
  dialogVisible.value = true
}

async function handleView(row) {
  try {
    const res = await getSupplier(row.id)
    if (res.code === 0) {
      currentSupplier.value = res.data
      detailVisible.value = true
    }
  } catch (error) {
    console.error('获取供应商详情失败:', error)
  }
}

async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value) {
      const res = await updateSupplier(formData.id, formData)
      if (res.code === 0) {
        ElMessage.success('更新成功')
        dialogVisible.value = false
        fetchData()
      }
    } else {
      const res = await createSupplier(formData)
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
    const res = await toggleSupplierStatus(row.id)
    if (res.code === 0) {
      ElMessage.success('操作成功')
    }
  } catch (error) {
    console.error('切换状态失败:', error)
    row.is_active = !row.is_active
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      `确定要删除供应商 "${row.name}" 吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const res = await deleteSupplier(row.id)
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
  fetchData()
})
</script>

<style scoped lang="scss">
.supplier-list {
  padding: 20px 0;

  .toolbar {
    margin-bottom: 16px;
  }

  .filter-card {
    margin-bottom: 20px;
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }
}
</style>
