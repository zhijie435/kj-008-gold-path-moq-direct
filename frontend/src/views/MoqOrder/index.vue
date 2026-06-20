<template>
  <div class="moq-order-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">国内小批量 MOQ 直发订单</h2>
        <p class="page-desc">管理国内小批量订单的全流程，从下单、确认、发货到签收</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>新建订单
        </el-button>
      </div>
    </div>

    <el-row :gutter="20" class="stats-cards">
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon primary">
            <el-icon><Document /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.total || 0 }}</div>
            <div class="stat-label">订单总数</div>
          </div>
        </div>
      </el-col>
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon warning">
            <el-icon><Clock /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.pending || 0 }}</div>
            <div class="stat-label">待确认</div>
          </div>
        </div>
      </el-col>
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon info">
            <el-icon><Setting /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.processing || 0 }}</div>
            <div class="stat-label">处理中</div>
          </div>
        </div>
      </el-col>
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon primary-light">
            <el-icon><Van /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.shipped || 0 }}</div>
            <div class="stat-label">已发货</div>
          </div>
        </div>
      </el-col>
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon success">
            <el-icon><CircleCheck /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.completed || 0 }}</div>
            <div class="stat-label">已完成</div>
          </div>
        </div>
      </el-col>
      <el-col :span="4">
        <div class="stat-card">
          <div class="stat-icon money">
            <el-icon><Money /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">¥{{ formatAmount(stats.today_amount || 0) }}</div>
            <div class="stat-label">今日成交额</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="订单号/客户姓名/电话"
            clearable
            style="width: 220px"
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
          <el-select v-model="queryParams.supplier_id" placeholder="全部" clearable filterable style="width: 180px">
            <el-option
              v-for="item in supplierList"
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
        <el-table-column prop="order_no" label="订单号" width="180" fixed="left">
          <template #default="{ row }">
            <span class="order-no" @click="handleView(row)">{{ row.order_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="客户信息" width="200">
          <template #default="{ row }">
            <div class="customer-info">
              <div class="customer-name">{{ row.customer_name }}</div>
              <div class="customer-phone">{{ row.customer_phone }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="收货地址" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.full_address }}</span>
          </template>
        </el-table-column>
        <el-table-column label="商品信息" min-width="180">
          <template #default="{ row }">
            <div class="goods-info">
              <div>共 {{ row.total_quantity }} 件商品</div>
              <div class="goods-count">{{ row.items?.length || 0 }} 种</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="发货进度" width="140" align="center">
          <template #default="{ row }">
            <el-progress
              :percentage="getShipProgress(row)"
              :stroke-width="8"
              :color="getShipProgressColor(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120" align="right">
          <template #default="{ row }">
            <div class="amount-box">
              <div class="payable">¥{{ formatAmount(row.payable_amount) }}</div>
              <div class="paid-info">
                已付: ¥{{ formatAmount(row.paid_amount) }}
                <el-tag v-if="row.unpaid_amount > 0" type="danger" size="small">欠{{ formatAmount(row.unpaid_amount) }}</el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTagType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="供应商" width="140">
          <template #default="{ row }">
            <span>{{ row.supplier?.name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="下单时间" width="160" align="center">
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
              @click="handleConfirm(row)"
            >确认</el-button>
            <el-button
              v-if="row.status === 'confirmed'"
              type="primary"
              link
              size="small"
              @click="handleStartProcess(row)"
            >开始处理</el-button>
            <el-button
              v-if="['confirmed', 'processing'].includes(row.status)"
              type="warning"
              link
              size="small"
              @click="handleCreateShipment(row)"
            >发货</el-button>
            <el-button
              v-if="row.status === 'shipped'"
              type="success"
              link
              size="small"
              @click="handleComplete(row)"
            >完成</el-button>
            <el-button
              v-if="!['completed', 'cancelled', 'refunded'].includes(row.status)"
              type="danger"
              link
              size="small"
              @click="handleCancel(row)"
            >取消</el-button>
            <el-button
              v-if="['pending', 'confirmed'].includes(row.status)"
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
      v-model="orderDialogVisible"
      :title="isEdit ? '编辑订单' : '新建订单'"
      width="900px"
      :close-on-click-modal="false"
      class="order-dialog"
    >
      <el-form
        ref="orderFormRef"
        :model="orderForm"
        :rules="orderFormRules"
        label-width="100px"
      >
        <el-divider content-position="left">客户信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="客户姓名" prop="customer_name">
              <el-input v-model="orderForm.customer_name" placeholder="请输入客户姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="联系电话" prop="customer_phone">
              <el-input v-model="orderForm.customer_phone" placeholder="请输入联系电话" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="供应商" prop="supplier_id">
              <el-select v-model="orderForm.supplier_id" placeholder="请选择供应商" filterable clearable style="width: 100%">
                <el-option
                  v-for="item in supplierList"
                  :key="item.id"
                  :label="item.name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="省份" prop="province">
              <el-input v-model="orderForm.province" placeholder="请输入省份" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="城市" prop="city">
              <el-input v-model="orderForm.city" placeholder="请输入城市" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="区县" prop="district">
              <el-input v-model="orderForm.district" placeholder="请输入区县" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="16">
            <el-form-item label="详细地址" prop="address">
              <el-input v-model="orderForm.address" placeholder="请输入详细地址" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="地址补充">
              <el-input v-model="orderForm.address_detail" placeholder="选填" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-divider content-position="left">商品明细</el-divider>
        <div class="goods-items-section">
          <el-table :data="orderForm.items" border size="small">
            <el-table-column label="商品名称" min-width="180">
              <template #default="{ row, $index }">
                <el-autocomplete
                  v-model="row.product_name"
                  :fetch-suggestions="searchProducts"
                  placeholder="输入商品名称/SKU"
                  @select="(item) => handleSelectProduct(item, $index)"
                  style="width: 100%"
                />
              </template>
            </el-table-column>
            <el-table-column label="SKU" width="120">
              <template #default="{ row }">
                <el-input v-model="row.product_sku" placeholder="SKU" />
              </template>
            </el-table-column>
            <el-table-column label="规格" width="120">
              <template #default="{ row }">
                <el-input v-model="row.specification" placeholder="规格" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="100">
              <template #default="{ row }">
                <el-input-number v-model="row.quantity" :min="1" @change="calculateItemTotal(row)" style="width: 100%" />
              </template>
            </el-table-column>
            <el-table-column label="单价(¥)" width="120">
              <template #default="{ row }">
                <el-input-number v-model="row.unit_price" :min="0" :precision="2" @change="calculateItemTotal(row)" style="width: 100%" />
              </template>
            </el-table-column>
            <el-table-column label="小计(¥)" width="120" align="right">
              <template #default="{ row }">
                <span class="item-total">{{ formatAmount(row.total_price || row.quantity * row.unit_price) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center">
              <template #default="{ $index }">
                <el-button type="danger" link size="small" @click="removeGoodsItem($index)">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="add-goods-btn">
            <el-button type="primary" plain @click="addGoodsItem">
              <el-icon><Plus /></el-icon>添加商品
            </el-button>
          </div>
        </div>

        <el-divider content-position="left">费用信息</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="运费(¥)" prop="shipping_fee">
              <el-input-number v-model="orderForm.shipping_fee" :min="0" :precision="2" @change="calculateTotal" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="优惠金额(¥)" prop="discount_amount">
              <el-input-number v-model="orderForm.discount_amount" :min="0" :precision="2" @change="calculateTotal" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="支付方式" prop="payment_method">
              <el-select v-model="orderForm.payment_method" placeholder="请选择" clearable style="width: 100%">
                <el-option
                  v-for="item in paymentOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <div class="amount-summary">
          <div class="summary-row">
            <span>商品总额：</span>
            <span class="summary-value">¥{{ formatAmount(orderForm.total_amount) }}</span>
          </div>
          <div class="summary-row">
            <span>运费：</span>
            <span class="summary-value">¥{{ formatAmount(orderForm.shipping_fee) }}</span>
          </div>
          <div class="summary-row">
            <span>优惠：</span>
            <span class="summary-value discount">-¥{{ formatAmount(orderForm.discount_amount) }}</span>
          </div>
          <el-divider />
          <div class="summary-row total">
            <span>应付金额：</span>
            <span class="summary-value total-amount">¥{{ formatAmount(orderForm.payable_amount) }}</span>
          </div>
        </div>

        <el-divider content-position="left">其他信息</el-divider>
        <el-form-item label="客户备注">
          <el-input v-model="orderForm.remark" type="textarea" :rows="2" placeholder="客户备注信息" />
        </el-form-item>
        <el-form-item label="内部备注">
          <el-input v-model="orderForm.internal_note" type="textarea" :rows="2" placeholder="内部备注，客户不可见" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="orderDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleOrderSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="detailVisible" title="订单详情" size="700px">
      <div v-if="currentOrder" class="order-detail">
        <el-descriptions title="基本信息" :column="2" border>
          <el-descriptions-item label="订单号">{{ currentOrder.order_no }}</el-descriptions-item>
          <el-descriptions-item label="订单状态">
            <el-tag :type="getStatusTagType(currentOrder.status)">
              {{ getStatusLabel(currentOrder.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ formatDateTime(currentOrder.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="确认时间">{{ currentOrder.confirmed_at ? formatDateTime(currentOrder.confirmed_at) : '-' }}</el-descriptions-item>
          <el-descriptions-item label="发货时间">{{ currentOrder.shipped_at ? formatDateTime(currentOrder.shipped_at) : '-' }}</el-descriptions-item>
          <el-descriptions-item label="完成时间">{{ currentOrder.completed_at ? formatDateTime(currentOrder.completed_at) : '-' }}</el-descriptions-item>
          <el-descriptions-item label="来源">{{ getSourceLabel(currentOrder.source) }}</el-descriptions-item>
          <el-descriptions-item label="供应商">{{ currentOrder.supplier?.name || '-' }}</el-descriptions-item>
        </el-descriptions>

        <el-descriptions title="客户信息" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="客户姓名">{{ currentOrder.customer_name }}</el-descriptions-item>
          <el-descriptions-item label="联系电话">{{ currentOrder.customer_phone }}</el-descriptions-item>
          <el-descriptions-item label="收货地址" :span="2">
            {{ currentOrder.full_address }}
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">商品明细</el-divider>
        <el-table :data="currentOrder.items" border size="small">
          <el-table-column prop="product_name" label="商品名称" min-width="160" />
          <el-table-column prop="product_sku" label="SKU" width="120" />
          <el-table-column prop="specification" label="规格" width="100" />
          <el-table-column prop="quantity" label="数量" width="80" align="center" />
          <el-table-column label="已发/总数" width="100" align="center">
            <template #default="{ row }">
              <span :class="{ 'text-danger': row.shipped_quantity < row.quantity }">
                {{ row.shipped_quantity }}/{{ row.quantity }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="单价" width="100" align="right">
            <template #default="{ row }">¥{{ formatAmount(row.unit_price) }}</template>
          </el-table-column>
          <el-table-column label="小计" width="100" align="right">
            <template #default="{ row }">¥{{ formatAmount(row.total_price) }}</template>
          </el-table-column>
        </el-table>

        <el-descriptions title="费用明细" :column="2" border style="margin-top: 20px">
          <el-descriptions-item label="商品总额">¥{{ formatAmount(currentOrder.total_amount) }}</el-descriptions-item>
          <el-descriptions-item label="运费">¥{{ formatAmount(currentOrder.shipping_fee) }}</el-descriptions-item>
          <el-descriptions-item label="优惠金额">-¥{{ formatAmount(currentOrder.discount_amount) }}</el-descriptions-item>
          <el-descriptions-item label="应付金额" class="text-primary">
            <strong>¥{{ formatAmount(currentOrder.payable_amount) }}</strong>
          </el-descriptions-item>
          <el-descriptions-item label="已付金额">¥{{ formatAmount(currentOrder.paid_amount) }}</el-descriptions-item>
          <el-descriptions-item label="未付金额">
            <el-tag v-if="currentOrder.unpaid_amount > 0" type="danger">¥{{ formatAmount(currentOrder.unpaid_amount) }}</el-tag>
            <el-tag v-else type="success">已付清</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="支付方式" :span="2">
            {{ getPaymentLabel(currentOrder.payment_method) || '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left" v-if="currentOrder.shipments?.length">发货记录</el-divider>
        <el-timeline v-if="currentOrder.shipments?.length">
          <el-timeline-item
            v-for="ship in currentOrder.shipments"
            :key="ship.id"
            :type="getShipmentTimelineType(ship.status)"
            :timestamp="formatDateTime(ship.created_at)"
          >
            <div class="shipment-item">
              <div class="shipment-header">
                <strong>{{ ship.shipment_no }}</strong>
                <el-tag size="small" :type="getShipmentStatusTagType(ship.status)">
                  {{ getShipmentStatusLabel(ship.status) }}
                </el-tag>
              </div>
              <div class="shipment-detail">
                <span>{{ ship.carrier_name }}</span>
                <el-link v-if="ship.tracking_url" type="primary" :href="ship.tracking_url" target="_blank">
                  {{ ship.tracking_no }}
                </el-link>
                <span v-else>{{ ship.tracking_no }}</span>
              </div>
              <div v-if="ship.remark" class="shipment-remark">{{ ship.remark }}</div>
            </div>
          </el-timeline-item>
        </el-timeline>

        <el-descriptions v-if="currentOrder.remark || currentOrder.internal_note" title="备注信息" :column="1" border style="margin-top: 20px">
          <el-descriptions-item v-if="currentOrder.remark" label="客户备注">{{ currentOrder.remark }}</el-descriptions-item>
          <el-descriptions-item v-if="currentOrder.internal_note" label="内部备注">{{ currentOrder.internal_note }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getMoqOrders,
  createMoqOrder,
  updateMoqOrder,
  deleteMoqOrder,
  confirmMoqOrder,
  cancelMoqOrder,
  startProcessingMoqOrder,
  completeMoqOrder,
  getMoqOrderStatusOptions,
  getMoqOrderSourceOptions,
  getMoqOrderPaymentOptions
} from '@/api/moqOrder'
import { getActiveSuppliers } from '@/api/supplier'
import { getProducts } from '@/api/product'
import { useRouter } from 'vue-router'

const router = useRouter()

const loading = ref(false)
const submitLoading = ref(false)
const tableData = ref([])
const total = ref(0)
const dateRange = ref([])
const statusOptions = ref([])
const sourceOptions = ref([])
const paymentOptions = ref([])
const supplierList = ref([])

const orderDialogVisible = ref(false)
const orderFormRef = ref(null)
const isEdit = ref(false)
const detailVisible = ref(false)
const currentOrder = ref(null)

const stats = ref({
  total: 0,
  pending: 0,
  processing: 0,
  shipped: 0,
  completed: 0,
  today_amount: 0,
  month_amount: 0
})

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  status: '',
  supplier_id: ''
})

const orderForm = reactive({
  id: null,
  customer_name: '',
  customer_phone: '',
  supplier_id: null,
  province: '',
  city: '',
  district: '',
  address: '',
  address_detail: '',
  items: [],
  shipping_fee: 0,
  discount_amount: 0,
  total_amount: 0,
  payable_amount: 0,
  payment_method: '',
  remark: '',
  internal_note: ''
})

const orderFormRules = {
  customer_name: [{ required: true, message: '请输入客户姓名', trigger: 'blur' }],
  customer_phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  province: [{ required: true, message: '请输入省份', trigger: 'blur' }],
  city: [{ required: true, message: '请输入城市', trigger: 'blur' }],
  district: [{ required: true, message: '请输入区县', trigger: 'blur' }],
  address: [{ required: true, message: '请输入详细地址', trigger: 'blur' }]
}

const statusTagMap = {
  pending: 'warning',
  confirmed: 'primary',
  processing: 'info',
  shipped: 'primary',
  completed: 'success',
  cancelled: 'danger',
  refunded: 'danger'
}

const statusLabelMap = {}
const sourceLabelMap = {}
const paymentLabelMap = {}
const shipmentStatusLabelMap = {}
const shipmentStatusTagMap = {}

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

function getSourceLabel(source) {
  return sourceLabelMap[source] || source
}

function getPaymentLabel(method) {
  return paymentLabelMap[method] || method
}

function getShipmentStatusLabel(status) {
  return shipmentStatusLabelMap[status] || status
}

function getShipmentStatusTagType(status) {
  return shipmentStatusTagMap[status] || 'info'
}

function getShipmentTimelineType(status) {
  const map = {
    pending: 'warning',
    picked: 'primary',
    shipped: 'primary',
    in_transit: 'primary',
    delivered: 'success',
    failed: 'danger',
    returned: 'danger'
  }
  return map[status] || 'info'
}

function getShipProgress(row) {
  if (!row.total_quantity) return 0
  return Math.round((row.shipped_quantity / row.total_quantity) * 100)
}

function getShipProgressColor(row) {
  const progress = getShipProgress(row)
  if (progress >= 100) return '#67c23a'
  if (progress > 0) return '#409eff'
  return '#e6a23c'
}

async function fetchOptions() {
  try {
    const [statusRes, sourceRes, paymentRes, supplierRes] = await Promise.all([
      getMoqOrderStatusOptions(),
      getMoqOrderSourceOptions(),
      getMoqOrderPaymentOptions(),
      getActiveSuppliers()
    ])
    statusOptions.value = statusRes.data
    sourceOptions.value = sourceRes.data
    paymentOptions.value = paymentRes.data
    supplierList.value = supplierRes.data

    statusRes.data.forEach(item => { statusLabelMap[item.value] = item.label })
    sourceRes.data.forEach(item => { sourceLabelMap[item.value] = item.label })
    paymentRes.data.forEach(item => { paymentLabelMap[item.value] = item.label })

    shipmentStatusLabelMap.pending = '待发货'
    shipmentStatusLabelMap.picked = '已揽收'
    shipmentStatusLabelMap.shipped = '已发出'
    shipmentStatusLabelMap.in_transit = '运输中'
    shipmentStatusLabelMap.delivered = '已签收'
    shipmentStatusLabelMap.failed = '派送失败'
    shipmentStatusLabelMap.returned = '已退回'

    shipmentStatusTagMap.pending = 'warning'
    shipmentStatusTagMap.picked = 'primary'
    shipmentStatusTagMap.shipped = 'primary'
    shipmentStatusTagMap.in_transit = 'info'
    shipmentStatusTagMap.delivered = 'success'
    shipmentStatusTagMap.failed = 'danger'
    shipmentStatusTagMap.returned = 'danger'
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
    const res = await getMoqOrders(params)
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
  queryParams.supplier_id = ''
  queryParams.page = 1
  dateRange.value = []
  fetchData()
}

function resetOrderForm() {
  Object.assign(orderForm, {
    id: null,
    customer_name: '',
    customer_phone: '',
    supplier_id: null,
    province: '',
    city: '',
    district: '',
    address: '',
    address_detail: '',
    items: [],
    shipping_fee: 0,
    discount_amount: 0,
    total_amount: 0,
    payable_amount: 0,
    payment_method: '',
    remark: '',
    internal_note: ''
  })
  addGoodsItem()
}

function handleAdd() {
  isEdit.value = false
  resetOrderForm()
  orderDialogVisible.value = true
}

function handleEdit(row) {
  isEdit.value = true
  Object.assign(orderForm, {
    id: row.id,
    customer_name: row.customer_name,
    customer_phone: row.customer_phone,
    supplier_id: row.supplier_id,
    province: row.province,
    city: row.city,
    district: row.district,
    address: row.address,
    address_detail: row.address_detail || '',
    items: JSON.parse(JSON.stringify(row.items || [])),
    shipping_fee: row.shipping_fee,
    discount_amount: row.discount_amount,
    total_amount: row.total_amount,
    payable_amount: row.payable_amount,
    payment_method: row.payment_method,
    remark: row.remark || '',
    internal_note: row.internal_note || ''
  })
  orderDialogVisible.value = true
}

function handleView(row) {
  currentOrder.value = row
  detailVisible.value = true
}

async function handleConfirm(row) {
  try {
    await ElMessageBox.confirm('确定要确认此订单吗？', '确认订单', { type: 'warning' })
    await confirmMoqOrder(row.id)
    ElMessage.success('订单确认成功')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleStartProcess(row) {
  try {
    await ElMessageBox.confirm('确定开始处理此订单吗？', '开始处理', { type: 'info' })
    await startProcessingMoqOrder(row.id)
    ElMessage.success('订单开始处理')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleComplete(row) {
  try {
    await ElMessageBox.confirm('确定标记此订单为已完成吗？', '完成订单', { type: 'success' })
    await completeMoqOrder(row.id)
    ElMessage.success('订单已完成')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleCancel(row) {
  try {
    const { value } = await ElMessageBox.prompt('请输入取消原因', '取消订单', {
      confirmButtonText: '确定取消',
      cancelButtonText: '返回',
      inputPlaceholder: '请输入取消原因'
    })
    await cancelMoqOrder(row.id, value)
    ElMessage.success('订单已取消')
    fetchData()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

function handleCreateShipment(row) {
  router.push({ path: '/shipments', query: { order_id: row.id } })
}

async function searchProducts(queryString, cb) {
  if (!queryString) {
    cb([])
    return
  }
  try {
    const res = await getProducts({ keyword: queryString, per_page: 10 })
    const list = res.data.map(item => ({
      value: item.name,
      id: item.id,
      name: item.name,
      sku: item.sku,
      specification: item.specification,
      unit_price: item.price,
      product_id: item.id,
      product_sku: item.sku
    }))
    cb(list)
  } catch (e) {
    cb([])
  }
}

function handleSelectProduct(item, index) {
  if (orderForm.items[index]) {
    orderForm.items[index].product_id = item.id
    orderForm.items[index].product_sku = item.sku
    orderForm.items[index].specification = item.specification
    orderForm.items[index].unit_price = item.unit_price
    calculateItemTotal(orderForm.items[index])
  }
}

function addGoodsItem() {
  orderForm.items.push({
    product_id: null,
    product_name: '',
    product_sku: '',
    specification: '',
    quantity: 1,
    unit_price: 0,
    total_price: 0,
    remark: ''
  })
}

function removeGoodsItem(index) {
  orderForm.items.splice(index, 1)
  calculateTotal()
}

function calculateItemTotal(row) {
  row.total_price = Number((row.quantity * row.unit_price).toFixed(2))
  calculateTotal()
}

function calculateTotal() {
  orderForm.total_amount = orderForm.items.reduce((sum, item) => sum + (item.quantity * item.unit_price || 0), 0)
  orderForm.total_amount = Number(orderForm.total_amount.toFixed(2))
  orderForm.payable_amount = Number((orderForm.total_amount + (orderForm.shipping_fee || 0) - (orderForm.discount_amount || 0)).toFixed(2))
}

async function handleOrderSubmit() {
  if (!orderFormRef.value) return
  try {
    await orderFormRef.value.validate()

    if (orderForm.items.length === 0) {
      ElMessage.warning('请至少添加一个商品')
      return
    }

    for (const item of orderForm.items) {
      if (!item.product_name || !item.product_sku) {
        ElMessage.warning('请完善商品信息')
        return
      }
    }

    submitLoading.value = true

    if (isEdit.value) {
      await updateMoqOrder(orderForm.id, orderForm)
      ElMessage.success('订单更新成功')
    } else {
      await createMoqOrder(orderForm)
      ElMessage.success('订单创建成功')
    }

    orderDialogVisible.value = false
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
.moq-order-page {
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

  .stats-cards {
    margin-bottom: 20px;
  }

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
      &.primary-light { background: linear-gradient(135deg, #74ebd5 0%, #9face6 100%); }
      &.money { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
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
    .order-no {
      color: #409eff;
      cursor: pointer;
      font-family: monospace;
    }

    .customer-info {
      .customer-name { font-weight: 500; color: #303133; }
      .customer-phone { font-size: 12px; color: #909399; margin-top: 2px; }
    }

    .goods-info {
      .goods-count {
        font-size: 12px;
        color: #909399;
        margin-top: 2px;
      }
    }

    .amount-box {
      text-align: right;
      .payable { font-weight: 600; color: #f56c6c; font-size: 14px; }
      .paid-info { font-size: 12px; color: #909399; margin-top: 2px; }
    }

    .text-danger { color: #f56c6c; }
    .text-primary { color: #409eff; }
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }

  .goods-items-section {
    .add-goods-btn { margin-top: 12px; }
    .item-total { font-weight: 600; color: #f56c6c; }
  }

  .amount-summary {
    background: #f5f7fa;
    padding: 16px;
    border-radius: 6px;
    margin-top: 12px;

    .summary-row {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      padding: 4px 0;

      .summary-value {
        min-width: 100px;
        text-align: right;
        color: #303133;
      }

      &.total {
        font-size: 16px;
        .summary-value.total-amount {
          color: #f56c6c;
          font-weight: 700;
          font-size: 18px;
        }
      }

      .discount { color: #67c23a; }
    }
  }

  .order-dialog {
    :deep(.el-dialog__body) {
      max-height: 70vh;
      overflow-y: auto;
    }
  }

  .order-detail {
    .shipment-item {
      .shipment-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
      }
      .shipment-detail {
        font-size: 13px;
        color: #606266;
        display: flex;
        gap: 14px;
      }
      .shipment-remark {
        font-size: 12px;
        color: #909399;
        margin-top: 4px;
      }
    }
  }
}
</style>
