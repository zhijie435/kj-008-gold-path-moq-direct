<template>
  <div class="shipment-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">发货管理</h2>
        <p class="page-desc">管理国内 MOQ 直发订单的发货、物流跟踪与签收</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建发货单
        </el-button>
      </div>
    </div>

    <el-row :gutter="20" class="stats-cards">
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon primary">
            <el-icon><Document /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.total || 0 }}</div>
            <div class="stat-label">发货单总数</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon warning">
            <el-icon><Clock /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.pending || 0 }}</div>
            <div class="stat-label">待发货</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon info">
            <el-icon><Van /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.shipped || 0 }}</div>
            <div class="stat-label">运输中</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon success">
            <el-icon><CircleCheck /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.delivered || 0 }}</div>
            <div class="stat-label">已签收</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="发货单号/运单号/订单号/客户"
            clearable
            style="width: 240px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="快递公司">
          <el-select v-model="queryParams.carrier_code" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in carrierOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
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

    <el-card class="table-card">
      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        border
        style="width: 100%"
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="shipment_no" label="发货单号" width="180" fixed="left">
          <template #default="{ row }">
            <span class="shipment-no" @click="handleView(row)">{{ row.shipment_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="关联订单" width="180">
          <template #default="{ row }">
            <span v-if="row.order" class="order-link">
              <el-icon><Link /></el-icon>
              {{ row.order.order_no }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="客户信息" width="200">
          <template #default="{ row }">
            <div v-if="row.order" class="customer-info">
              <div class="customer-name">{{ row.order.customer_name }}</div>
              <div class="customer-phone">{{ row.order.customer_phone }}</div>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="收货地址" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.order">{{ row.order.full_address }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="物流信息" width="220">
          <template #default="{ row }">
            <div class="logistics-info">
              <div class="carrier">
                <el-icon><Van /></el-icon>
                {{ row.carrier_name }}
              </div>
              <div class="tracking">
                <el-link
                  v-if="row.tracking_url"
                  type="primary"
                  :href="row.tracking_url"
                  target="_blank"
                  size="small"
                >
                  {{ row.tracking_no }}
                </el-link>
                <span v-else>{{ row.tracking_no }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="包裹信息" width="140" align="center">
          <template #default="{ row }">
            <div class="package-info">
              <div>{{ row.package_count }} 件</div>
              <div class="weight">{{ row.weight || 0 }} kg</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="运费" width="100" align="right">
          <template #default="{ row }">
            <span>¥{{ formatAmount(row.shipping_cost) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160" align="center">
          <template #default="{ row }">
            <span>{{ formatDateTime(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="320" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">查看</el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="success"
              link
              size="small"
              @click="handleShip(row)"
            >发货</el-button>
            <el-button
              v-if="['pending', 'shipped'].includes(row.status)"
              type="primary"
              link
              size="small"
              @click="handleMarkPicked(row)"
            >揽收</el-button>
            <el-button
              v-if="['picked', 'shipped'].includes(row.status)"
              type="primary"
              link
              size="small"
              @click="handleMarkInTransit(row)"
            >运输中</el-button>
            <el-button
              v-if="['picked', 'shipped', 'in_transit'].includes(row.status)"
              type="success"
              link
              size="small"
              @click="handleMarkDelivered(row)"
            >签收</el-button>
            <el-button
              v-if="!['delivered', 'returned'].includes(row.status)"
              type="warning"
              link
              size="small"
              @click="handleMarkFailed(row)"
            >异常</el-button>
            <el-button
              v-if="row.status !== 'delivered'"
              type="danger"
              link
              size="small"
              @click="handleMarkReturned(row)"
            >退回</el-button>
            <el-button
              v-if="row.status === 'pending'"
              type="primary"
              link
              size="small"
              @click="handleEdit(row)"
            >编辑</el-button>
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
      v-model="shipmentDialogVisible"
      :title="isEdit ? '编辑发货单' : '新建发货单'"
      width="760px"
      :close-on-click-modal="false"
      class="shipment-dialog"
    >
      <el-form
        ref="shipmentFormRef"
        :model="shipmentForm"
        :rules="shipmentFormRules"
        label-width="100px"
      >
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="关联订单" prop="moq_order_id">
              <el-select
                v-model="shipmentForm.moq_order_id"
                placeholder="请选择订单"
                filterable
                style="width: 100%"
                @change="handleOrderChange"
              >
                <el-option
                  v-for="item in orderList"
                  :key="item.id"
                  :label="`${item.order_no} - ${item.customer_name}`"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider v-if="selectedOrder" content-position="left">订单商品发货</el-divider>
        <el-table
          v-if="selectedOrder"
          :data="shipmentForm.ship_items"
          border
          size="small"
          style="margin-bottom: 16px"
        >
          <el-table-column label="商品名称" min-width="180">
            <template #default="{ row }">
              <div>
                <div>{{ row.product_name }}</div>
                <div style="font-size: 12px; color: #909399">{{ row.product_sku }}</div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="规格" width="120" prop="specification" />
          <el-table-column label="已订/已发" width="120" align="center">
            <template #default="{ row }">
              <span>{{ row.shipped_quantity }}/{{ row.quantity }}</span>
            </template>
          </el-table-column>
          <el-table-column label="本次发货" width="160">
            <template #default="{ row }">
              <el-input-number
                v-model="row.ship_quantity"
                :min="0"
                :max="row.quantity - row.shipped_quantity"
                style="width: 100%"
              />
            </template>
          </el-table-column>
        </el-table>

        <el-divider content-position="left">物流信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="快递公司" prop="carrier_code">
              <el-select v-model="shipmentForm.carrier_code" placeholder="请选择" style="width: 100%" @change="handleCarrierChange">
                <el-option
                  v-for="item in carrierOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="快递名称" prop="carrier_name">
              <el-input v-model="shipmentForm.carrier_name" placeholder="请输入快递名称" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="运单号" prop="tracking_no">
              <el-input v-model="shipmentForm.tracking_no" placeholder="请输入运单号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="运输方式" prop="shipping_method">
              <el-select v-model="shipmentForm.shipping_method" placeholder="请选择" clearable style="width: 100%">
                <el-option label="标准快递" value="standard" />
                <el-option label="加急快递" value="express" />
                <el-option label="物流专线" value="freight" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">包裹信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="包裹数量" prop="package_count">
              <el-input-number v-model="shipmentForm.package_count" :min="1" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="重量(kg)" prop="weight">
              <el-input-number v-model="shipmentForm.weight" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="运费(¥)" prop="shipping_cost">
              <el-input-number v-model="shipmentForm.shipping_cost" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="备注">
          <el-input v-model="shipmentForm.remark" type="textarea" :rows="2" placeholder="备注信息" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="shipmentDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleShipmentSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="发货单详情" size="600px">
      <div v-if="currentShipment" class="shipment-detail">
        <el-descriptions title="基本信息" :column="2" border>
          <el-descriptions-item label="发货单号">{{ currentShipment.shipment_no }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusTagType(currentShipment.status)">
              {{ getStatusLabel(currentShipment.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="关联订单">
            <span v-if="currentShipment.order">{{ currentShipment.order.order_no }}</span>
            <span v-else>-</span>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatDateTime(currentShipment.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="发货时间">{{ currentShipment.shipped_at ? formatDateTime(currentShipment.shipped_at) : '-' }}</el-descriptions-item>
          <el-descriptions-item label="签收时间">{{ currentShipment.delivered_at ? formatDateTime(currentShipment.delivered_at) : '-' }}</el-descriptions-item>
        </el-descriptions>

        <el-descriptions v-if="currentShipment.order" title="客户信息" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="客户姓名">{{ currentShipment.order.customer_name }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ currentShipment.order.customer_phone }}</el-descriptions-item>
          <el-descriptions-item label="收货地址" :span="2">
            {{ currentShipment.order.full_address }}
          </el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="物流信息" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="快递公司">{{ currentShipment.carrier_name }}</el-descriptions-item>
          <el-descriptions-item label="运输方式">{{ currentShipment.shipping_method || '-' }}</el-descriptions-item>
          <el-descriptions-item label="运单号" :span="2">
            <el-link
              v-if="currentShipment.tracking_url"
              type="primary"
              :href="currentShipment.tracking_url"
              target="_blank"
            >
              {{ currentShipment.tracking_no }}
            </el-link>
            <span v-else>{{ currentShipment.tracking_no }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="包裹信息" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="包裹数量">{{ currentShipment.package_count }} 件</el-descriptions-item>
          <el-descriptions-item label="重量">{{ currentShipment.weight || 0 }} kg</el-descriptions-item>
          <el-descriptions-item label="运费">¥{{ formatAmount(currentShipment.shipping_cost) }}</el-descriptions-item>
        </el-descriptions>

        <el-divider v-if="currentShipment.order?.items?.length" content-position="left">发货商品</el-divider>
        <el-table v-if="currentShipment.order?.items" :data="currentShipment.order.items" border size="small" style="margin-top: 10px">
          <el-table-column prop="product_name" label="商品名称" min-width="160" />
          <el-table-column prop="product_sku" label="SKU" width="120" />
          <el-table-column prop="specification" label="规格" width="100" />
          <el-table-column label="数量" width="180" align="center">
            <template #default="{ row }">
              <el-progress :percentage="row.quantity ? (row.shipped_quantity / row.quantity * 100) : 0" />
              <span style="font-size: 12px">{{ row.shipped_quantity }}/{{ row.quantity }}</span>
            </template>
          </el-table-column>
        </el-table>

        <el-descriptions v-if="currentShipment.remark" title="备注" :column="1" border style="margin-top: 20px">
          <el-descriptions-item label="备注">{{ currentShipment.remark }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getShipments,
  createShipment,
  updateShipment,
  deleteShipment,
  shipShipment,
  markShipmentPicked,
  markShipmentInTransit,
  markShipmentDelivered,
  markShipmentFailed,
  markShipmentReturned,
  getShipmentStatusOptions,
  getShipmentCarrierOptions
} from '@/api/shipment'
import { getMoqOrders } from '@/api/moqOrder'

const route = useRoute()

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const dateRange = ref([])
const statusOptions = ref([])
const carrierOptions = ref([])
const orderList = ref([])
const selectedOrder = ref(null)

const shipmentDialogVisible = ref(false)
const shipmentFormRef = ref(null)
const isEdit = ref(false)
const detailVisible = ref(false)
const currentShipment = ref(null)

const stats = ref({
  total: 0,
  pending: 0,
  shipped: 0,
  delivered: 0,
  exception: 0
})

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  status: '',
  carrier_code: '',
  moq_order_id: ''
})

const shipmentForm = reactive({
  id: null,
  moq_order_id: null,
  carrier_code: '',
  carrier_name: '',
  tracking_no: '',
  shipping_method: '',
  package_count: 1,
  weight: 0,
  shipping_cost: 0,
  remark: '',
  ship_items: []
})

const shipmentFormRules = {
  moq_order_id: [{ required: true, message: '请选择关联订单', trigger: 'change' }],
  carrier_code: [{ required: true, message: '请选择快递公司', trigger: 'change' }],
  carrier_name: [{ required: true, message: '请输入快递名称', trigger: 'blur' }],
  tracking_no: [{ required: true, message: '请输入运单号', trigger: 'blur' }]
}

const statusTagMap = {
  pending: 'warning',
  picked: 'primary',
  shipped: 'primary',
  in_transit: 'info',
  delivered: 'success',
  failed: 'danger',
  returned: 'danger'
}

const statusLabelMap = {}
const carrierLabelMap = {}

function formatAmount(val) {
  return Number(val || 0).toFixed(2)
}

function formatDateTime(val) {
  if (!val) return '-'
  const d = new Date(val)
  return d.toLocaleString('zh-CN', { hour12: false })
}

function getStatusTagType(status) {
  return statusTagMap[status] || 'info'
}

function getStatusLabel(status) {
  return statusLabelMap[status] || status
}

async function fetchOptions() {
  try {
    const [statusRes, carrierRes] = await Promise.all([
      getShipmentStatusOptions(),
      getShipmentCarrierOptions()
    ])
    statusOptions.value = statusRes.data
    carrierOptions.value = carrierRes.data

    statusRes.data.forEach(item => { statusLabelMap[item.value] = item.label })
    carrierRes.data.forEach(item => { carrierLabelMap[item.value] = item.label })
  } catch (e) {
    console.error(e)
  }
}

async function fetchOrders() {
  try {
    const res = await getMoqOrders({ status: 'processing,confirmed,shipped', per_page: 100 })
    orderList.value = res.data
  } catch (e) {
    console.error(e)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const params = { ...queryParams }
    if (dateRange.value?.length === 2) {
      params.date_from = dateRange.value[0]
      params.date_to = dateRange.value[1]
    }
    const res = await getShipments(params)
    tableData.value = res.data
    total.value = res.total
    if (res.stats) {
      stats.value = res.stats
    }
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
  queryParams.status = ''
  queryParams.carrier_code = ''
  queryParams.moq_order_id = ''
  queryParams.page = 1
  dateRange.value = []
  fetchData()
}

function resetShipmentForm() {
  Object.assign(shipmentForm, {
    id: null,
    moq_order_id: null,
    carrier_code: '',
    carrier_name: '',
    tracking_no: '',
    shipping_method: '',
    package_count: 1,
    weight: 0,
    shipping_cost: 0,
    remark: '',
    ship_items: []
  })
  selectedOrder.value = null
}

function handleAdd() {
  isEdit.value = false
  resetShipmentForm()
  fetchOrders()
  shipmentDialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  Object.assign(shipmentForm, {
    id: row.id,
    moq_order_id: row.moq_order_id,
    carrier_code: row.carrier_code,
    carrier_name: row.carrier_name,
    tracking_no: row.tracking_no,
    shipping_method: row.shipping_method,
    package_count: row.package_count,
    weight: row.weight,
    shipping_cost: row.shipping_cost,
    remark: row.remark || '',
    ship_items: []
  })
  selectedOrder.value = row.order
  fetchOrders()
  shipmentDialogVisible.value = true
}

function handleView(row) {
  currentShipment.value = row
  detailVisible.value = true
}

function handleCarrierChange(val) {
  const carrier = carrierOptions.value.find(c => c.value === val)
  if (carrier) {
    shipmentForm.carrier_name = carrier.label
  }
}

async function handleOrderChange(orderId) {
  const order = orderList.value.find(o => o.id === orderId)
  selectedOrder.value = order || null
  if (order && order.items) {
    shipmentForm.ship_items = order.items.map(item => ({
      order_item_id: item.id,
      product_id: item.product_id,
      product_name: item.product_name,
      product_sku: item.product_sku,
      specification: item.specification,
      quantity: item.quantity,
      shipped_quantity: item.shipped_quantity,
      ship_quantity: Math.max(0, item.quantity - item.shipped_quantity)
    }))
  } else {
    shipmentForm.ship_items = []
  }
}

async function handleShip(row) {
  try {
    await ElMessageBox.confirm('确定标记此发货单为已发货吗？', '确认发货', { type: 'success' })
    await shipShipment(row.id)
    ElMessage.success('发货成功')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleMarkPicked(row) {
  try {
    await markShipmentPicked(row.id)
    ElMessage.success('已标记揽收')
    fetchData()
  } catch (e) {
    console.error(e)
  }
}

async function handleMarkInTransit(row) {
  try {
    await markShipmentInTransit(row.id)
    ElMessage.success('已标记运输中')
    fetchData()
  } catch (e) {
    console.error(e)
  }
}

async function handleMarkDelivered(row) {
  try {
    await ElMessageBox.confirm('确定标记此发货单为已签收吗？', '确认签收', { type: 'success' })
    await markShipmentDelivered(row.id)
    ElMessage.success('已标记签收')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleMarkFailed(row) {
  try {
    const { value } = await ElMessageBox.prompt('请输入异常原因', '标记派送异常', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputPlaceholder: '请输入异常原因'
    })
    await markShipmentFailed(row.id, value)
    ElMessage.success('已标记异常')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleMarkReturned(row) {
  try {
    const { value } = await ElMessageBox.prompt('请输入退回原因', '标记退回', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputPlaceholder: '请输入退回原因'
    })
    await markShipmentReturned(row.id, value)
    ElMessage.success('已标记退回')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleShipmentSubmit() {
  if (!shipmentFormRef.value) return
  try {
    await shipmentFormRef.value.validate()
    submitLoading.value = true

    const submitData = {
      ...shipmentForm,
      ship_items: shipmentForm.ship_items
        .filter(item => item.ship_quantity > 0)
        .map(item => ({
          order_item_id: item.order_item_id,
          quantity: item.ship_quantity
        }))
    }

    if (isEdit.value) {
      await updateShipment(shipmentForm.id, shipmentForm)
      ElMessage.success('发货单更新成功')
    } else {
      await createShipment(submitData)
      ElMessage.success('发货单创建成功')
    }

    shipmentDialogVisible.value = false
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

  if (route.query.order_id) {
    queryParams.moq_order_id = route.query.order_id
  }
})
</script>

<style scoped lang="scss">
.shipment-page {
  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;

    .page-title {
      font-size: 20px;
      color: #303133;
      margin: 0 0 4px 0;
    }
    .page-desc {
      font-size: 13px;
      color: #909399;
      margin: 0;
    }
  }

  .stats-cards { margin-bottom: 20px; }

  .stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);

    .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      color: #fff;

      &.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      &.warning { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
      &.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
      &.success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    }

    .stat-content {
      .stat-value {
        font-size: 20px;
        font-weight: 600;
        color: #303133;
        line-height: 1.2;
      }
      .stat-label {
        font-size: 12px;
        color: #909399;
        margin-top: 4px;
      }
    }
  }

  .filter-card { margin-bottom: 20px; }

  .table-card {
    .shipment-no {
      color: #409eff;
      cursor: pointer;
      font-family: monospace;
    }
    .order-link {
      color: #409eff;
      display: flex;
      align-items: center;
      gap: 4px;
      font-family: monospace;
      font-size: 12px;
    }
    .customer-info {
      .customer-name { font-weight: 500; color: #303133; }
      .customer-phone { font-size: 12px; color: #909399; margin-top: 2px; }
    }
    .logistics-info {
      .carrier {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #303133;
        font-weight: 500;
      }
      .tracking {
        margin-top: 4px;
        font-size: 12px;
      }
    }
    .package-info {
      .weight { font-size: 12px; color: #909399; margin-top: 2px; }
    }
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }

  .shipment-dialog {
    :deep(.el-dialog__body) {
      max-height: 70vh;
      overflow-y: auto;
    }
  }
}
</style>
