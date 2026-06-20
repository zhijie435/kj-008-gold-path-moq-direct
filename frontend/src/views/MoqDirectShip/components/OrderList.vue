<template>
  <div class="order-list">
    <el-card class="filter-card" shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="订单号/客户名/手机号"
            clearable
            style="width: 240px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
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
        <el-form-item label="下单时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            style="width: 280px"
          />
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
        <el-table-column prop="order_no" label="订单号" width="180">
          <template #default="{ row }">
            <span class="order-no" @click="handleView(row)">{{ row.order_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="customer_name" label="客户姓名" width="120" />
        <el-table-column prop="customer_phone" label="联系电话" width="140" />
        <el-table-column label="收货地址" min-width="200">
          <template #default="{ row }">
            <el-tooltip :content="row.full_address" placement="top">
              <span class="address-text">{{ row.full_address }}</span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column prop="supplier.name" label="供应商" width="140">
          <template #default="{ row }">
            {{ row.supplier?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="total_quantity" label="数量" width="80" align="center">
          <template #default="{ row }">
            {{ row.items?.reduce((sum, item) => sum + item.quantity, 0) || 0 }}
          </template>
        </el-table-column>
        <el-table-column prop="payable_amount" label="应付金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount">¥{{ formatMoney(row.payable_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="下单时间" width="170" />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">
              查看
            </el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="success"
              link
              size="small"
              @click="handleConfirm(row)"
            >
              确认
            </el-button>
            <el-button
              v-if="row.status === 'confirmed' || row.status === 'processing'"
              type="warning"
              link
              size="small"
              @click="handleShip(row)"
            >
              发货
            </el-button>
            <el-button
              v-if="row.status === 'pending' || row.status === 'confirmed'"
              type="danger"
              link
              size="small"
              @click="handleCancel(row)"
            >
              取消
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
      v-model="shipDialogVisible"
      title="订单发货"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form ref="shipFormRef" :model="shipForm" :rules="shipRules" label-width="100px">
        <el-form-item label="物流公司" prop="carrier_code">
          <el-select v-model="shipForm.carrier_code" placeholder="请选择物流公司" style="width: 100%">
            <el-option
              v-for="item in carrierOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="物流单号" prop="tracking_no">
          <el-input v-model="shipForm.tracking_no" placeholder="请输入物流单号" />
        </el-form-item>
        <el-form-item label="运费" prop="shipping_cost">
          <el-input-number v-model="shipForm.shipping_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="重量(kg)" prop="weight">
          <el-input-number v-model="shipForm.weight" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="包裹数" prop="package_count">
          <el-input-number v-model="shipForm.package_count" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="shipForm.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="shipLoading" @click="handleShipSubmit">
          确认发货
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="cancelDialogVisible"
      title="取消订单"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form label-width="80px">
        <el-form-item label="取消原因">
          <el-input v-model="cancelReason" type="textarea" :rows="3" placeholder="请输入取消原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="cancelDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="cancelLoading" @click="handleCancelSubmit">
          确认取消
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh } from '@element-plus/icons-vue'
import {
  getMoqOrders,
  confirmMoqOrder,
  shipMoqOrder,
  cancelMoqOrder,
  getOrderStatusOptions,
  getAllSuppliers,
  getCarrierOptions
} from '@/api/moqDirectShip'

const emit = defineEmits(['create', 'view', 'refresh'])

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const statusOptions = ref([])
const supplierOptions = ref([])
const carrierOptions = ref([])

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  status: '',
  supplier_id: '',
  start_date: '',
  end_date: ''
})

const dateRange = ref([])

const shipDialogVisible = ref(false)
const shipLoading = ref(false)
const shipFormRef = ref(null)
const currentShipOrder = ref(null)
const shipForm = reactive({
  carrier_code: '',
  carrier_name: '',
  tracking_no: '',
  shipping_cost: 0,
  weight: 0,
  package_count: 1,
  remark: ''
})

const shipRules = {
  carrier_code: [{ required: true, message: '请选择物流公司', trigger: 'change' }],
  tracking_no: [{ required: true, message: '请输入物流单号', trigger: 'blur' }]
}

const cancelDialogVisible = ref(false)
const cancelLoading = ref(false)
const currentCancelOrder = ref(null)
const cancelReason = ref('')

function formatMoney(value) {
  if (!value && value !== 0) return '0.00'
  return Number(value).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

function getStatusLabel(status) {
  const item = statusOptions.value.find(s => s.value === status)
  return item ? item.label : status
}

function getStatusType(status) {
  const typeMap = {
    pending: 'warning',
    confirmed: 'primary',
    processing: 'info',
    shipped: '',
    completed: 'success',
    cancelled: 'danger',
    refunded: 'danger'
  }
  return typeMap[status] || 'info'
}

async function fetchStatusOptions() {
  try {
    const res = await getOrderStatusOptions()
    if (res.code === 0) {
      statusOptions.value = res.data
    }
  } catch (error) {
    console.error('获取状态选项失败:', error)
  }
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

async function fetchCarrierOptions() {
  try {
    const res = await getCarrierOptions()
    if (res.code === 0) {
      carrierOptions.value = res.data
    }
  } catch (error) {
    console.error('获取物流公司选项失败:', error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }

    const res = await getMoqOrders(params)
    if (res.code === 0) {
      tableData.value = res.data.list
      total.value = res.data.total
    }
  } catch (error) {
    console.error('获取订单列表失败:', error)
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
  queryParams.status = ''
  queryParams.supplier_id = ''
  dateRange.value = []
  queryParams.page = 1
  fetchData()
}

function handleView(row) {
  emit('view', row)
}

async function handleConfirm(row) {
  try {
    await ElMessageBox.confirm(
      `确定要确认订单 "${row.order_no}" 吗？`,
      '提示',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    const res = await confirmMoqOrder(row.id)
    if (res.code === 0) {
      ElMessage.success('确认成功')
      fetchData()
      emit('refresh')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('确认订单失败:', error)
    }
  }
}

function handleShip(row) {
  currentShipOrder.value = row
  Object.assign(shipForm, {
    carrier_code: '',
    carrier_name: '',
    tracking_no: '',
    shipping_cost: 0,
    weight: 0,
    package_count: 1,
    remark: ''
  })
  shipDialogVisible.value = true
}

async function handleShipSubmit() {
  if (!shipFormRef.value) return

  try {
    await shipFormRef.value.validate()
    shipLoading.value = true

    const items = currentShipOrder.value.items?.map(item => ({
      order_item_id: item.id,
      quantity: item.quantity - item.shipped_quantity
    })) || []

    const res = await shipMoqOrder(currentShipOrder.value.id, {
      ...shipForm,
      items
    })

    if (res.code === 0) {
      ElMessage.success('发货成功')
      shipDialogVisible.value = false
      fetchData()
      emit('refresh')
    }
  } catch (error) {
    console.error('发货失败:', error)
  } finally {
    shipLoading.value = false
  }
}

function handleCancel(row) {
  currentCancelOrder.value = row
  cancelReason.value = ''
  cancelDialogVisible.value = true
}

async function handleCancelSubmit() {
  if (!cancelReason.value.trim()) {
    ElMessage.warning('请输入取消原因')
    return
  }

  try {
    cancelLoading.value = true
    const res = await cancelMoqOrder(currentCancelOrder.value.id, {
      reason: cancelReason.value
    })

    if (res.code === 0) {
      ElMessage.success('取消成功')
      cancelDialogVisible.value = false
      fetchData()
      emit('refresh')
    }
  } catch (error) {
    console.error('取消订单失败:', error)
  } finally {
    cancelLoading.value = false
  }
}

onMounted(() => {
  fetchStatusOptions()
  fetchSupplierOptions()
  fetchCarrierOptions()
  fetchData()
})
</script>

<style scoped lang="scss">
.order-list {
  padding: 20px 0;

  .filter-card {
    margin-bottom: 20px;
  }

  .order-no {
    color: #409eff;
    cursor: pointer;
    font-weight: 500;
  }

  .address-text {
    color: #606266;
    font-size: 13px;
  }

  .amount {
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
