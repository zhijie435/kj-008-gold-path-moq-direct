<template>
  <el-drawer
    :model-value="modelValue"
    @update:model-value="handleClose"
    title="订单详情"
    size="700px"
    destroy-on-close
  >
    <div v-if="order" class="order-detail">
      <div class="detail-header">
        <div class="order-status">
          <el-tag :type="getStatusType(order.status)" size="large">
            {{ getStatusLabel(order.status) }}
          </el-tag>
          <span class="order-no">{{ order.order_no }}</span>
        </div>
        <div class="order-actions">
          <el-button
            v-if="order.status === 'pending'"
            type="primary"
            @click="handleConfirm"
          >
            确认订单
          </el-button>
          <el-button
            v-if="order.status === 'confirmed' || order.status === 'processing'"
            type="success"
            @click="handleShip"
          >
            发货
          </el-button>
          <el-button
            v-if="order.status === 'shipped'"
            type="success"
            @click="handleComplete"
          >
            完成订单
          </el-button>
          <el-button
            v-if="order.status === 'pending' || order.status === 'confirmed'"
            type="danger"
            @click="handleCancel"
          >
            取消订单
          </el-button>
        </div>
      </div>

      <el-descriptions :column="2" border class="info-section">
        <el-descriptions-item label="下单时间">{{ order.created_at }}</el-descriptions-item>
        <el-descriptions-item label="订单来源">{{ getSourceLabel(order.source) }}</el-descriptions-item>
        <el-descriptions-item label="供应商">{{ order.supplier?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="支付方式">
          {{ order.payment_method ? getPaymentLabel(order.payment_method) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="支付状态">
          <el-tag :type="order.is_fully_paid ? 'success' : 'warning'" size="small">
            {{ order.is_fully_paid ? '已付清' : '未付清' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="付款时间">{{ order.paid_at || '-' }}</el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">收货信息</h4>
      <el-descriptions :column="2" border class="info-section">
        <el-descriptions-item label="收货人">{{ order.customer_name }}</el-descriptions-item>
        <el-descriptions-item label="联系电话">{{ order.customer_phone }}</el-descriptions-item>
        <el-descriptions-item label="收货地址" :span="2">
          {{ order.full_address }}
          <span v-if="order.address_detail">（{{ order.address_detail }}）</span>
        </el-descriptions-item>
      </el-descriptions>

      <h4 class="section-title">商品信息</h4>
      <el-table :data="order.items" border class="items-table">
        <el-table-column prop="product_name" label="商品名称" min-width="200" />
        <el-table-column prop="product_sku" label="SKU" width="140" />
        <el-table-column prop="specification" label="规格" width="140">
          <template #default="{ row }">
            {{ row.specification || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="unit_price" label="单价" width="100" align="right">
          <template #default="{ row }">
            ¥{{ formatMoney(row.unit_price) }}
          </template>
        </el-table-column>
        <el-table-column prop="quantity" label="数量" width="80" align="center" />
        <el-table-column prop="total_price" label="小计" width="100" align="right">
          <template #default="{ row }">
            ¥{{ formatMoney(row.total_price) }}
          </template>
        </el-table-column>
        <el-table-column prop="shipped_quantity" label="已发数量" width="100" align="center">
          <template #default="{ row }">
            {{ row.shipped_quantity }} / {{ row.quantity }}
          </template>
        </el-table-column>
      </el-table>

      <h4 class="section-title">金额明细</h4>
      <div class="amount-section">
        <div class="amount-item">
          <span class="label">商品总额</span>
          <span class="value">¥{{ formatMoney(order.total_amount) }}</span>
        </div>
        <div class="amount-item">
          <span class="label">运费</span>
          <span class="value">¥{{ formatMoney(order.shipping_fee || 0) }}</span>
        </div>
        <div class="amount-item">
          <span class="label">优惠</span>
          <span class="value discount">-¥{{ formatMoney(order.discount_amount || 0) }}</span>
        </div>
        <div class="amount-item total">
          <span class="label">应付金额</span>
          <span class="value">¥{{ formatMoney(order.payable_amount) }}</span>
        </div>
        <div class="amount-item">
          <span class="label">已付金额</span>
          <span class="value paid">¥{{ formatMoney(order.paid_amount || 0) }}</span>
        </div>
        <div class="amount-item">
          <span class="label">待付金额</span>
          <span class="value unpaid">¥{{ formatMoney(order.unpaid_amount || 0) }}</span>
        </div>
      </div>

      <h4 class="section-title">物流信息</h4>
      <div v-if="order.shipments && order.shipments.length > 0">
        <div v-for="shipment in order.shipments" :key="shipment.id" class="shipment-card">
          <div class="shipment-header">
            <span class="shipment-no">{{ shipment.shipment_no }}</span>
            <el-tag :type="getShipmentStatusType(shipment.status)" size="small">
              {{ getShipmentStatusLabel(shipment.status) }}
            </el-tag>
          </div>
          <div class="shipment-info">
            <span>物流公司：{{ shipment.carrier_name }}</span>
            <span>物流单号：{{ shipment.tracking_no }}</span>
            <span>发货时间：{{ shipment.shipped_at || '-' }}</span>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无物流信息" :image-size="80" />

      <h4 class="section-title">备注信息</h4>
      <el-descriptions :column="1" border class="info-section">
        <el-descriptions-item label="客户备注">{{ order.remark || '无' }}</el-descriptions-item>
        <el-descriptions-item label="内部备注">{{ order.internal_note || '无' }}</el-descriptions-item>
      </el-descriptions>
    </div>

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

    <el-dialog
      v-model="shipDialogVisible"
      title="订单发货"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form ref="shipFormRef" :model="shipForm" :rules="shipRules" label-width="100px">
        <el-form-item label="物流公司" prop="carrier_code">
          <el-select v-model="shipForm.carrier_code" placeholder="请选择" style="width: 100%">
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
        <el-form-item label="运费">
          <el-input-number v-model="shipForm.shipping_cost" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="重量(kg)">
          <el-input-number v-model="shipForm.weight" :min="0" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="shipForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="shipLoading" @click="handleShipSubmit">
          确认发货
        </el-button>
      </template>
    </el-dialog>
  </el-drawer>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getMoqOrder,
  confirmMoqOrder,
  shipMoqOrder,
  completeMoqOrder,
  cancelMoqOrder,
  getOrderStatusOptions,
  getOrderSourceOptions,
  getPaymentOptions,
  getCarrierOptions,
  getShipmentStatusOptions
} from '@/api/moqDirectShip'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'refresh'])

const orderDetail = ref(null)
const statusOptions = ref([])
const sourceOptions = ref([])
const paymentOptions = ref([])
const carrierOptions = ref([])
const shipmentStatusOptions = ref([])

const cancelDialogVisible = ref(false)
const cancelLoading = ref(false)
const cancelReason = ref('')

const shipDialogVisible = ref(false)
const shipLoading = ref(false)
const shipFormRef = ref(null)
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

watch(() => props.modelValue, (val) => {
  if (val && props.order) {
    fetchOrderDetail()
    fetchOptions()
  }
})

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

function getSourceLabel(source) {
  const item = sourceOptions.value.find(s => s.value === source)
  return item ? item.label : source
}

function getPaymentLabel(payment) {
  const item = paymentOptions.value.find(p => p.value === payment)
  return item ? item.label : payment
}

function getShipmentStatusLabel(status) {
  const item = shipmentStatusOptions.value.find(s => s.value === status)
  return item ? item.label : status
}

function getShipmentStatusType(status) {
  const typeMap = {
    pending: 'warning',
    picked: 'primary',
    shipped: '',
    in_transit: 'info',
    delivered: 'success',
    failed: 'danger',
    returned: 'danger'
  }
  return typeMap[status] || 'info'
}

async function fetchOptions() {
  try {
    const [statusRes, sourceRes, paymentRes, carrierRes, shipStatusRes] = await Promise.all([
      getOrderStatusOptions(),
      getOrderSourceOptions(),
      getPaymentOptions(),
      getCarrierOptions(),
      getShipmentStatusOptions()
    ])

    if (statusRes.code === 0) statusOptions.value = statusRes.data
    if (sourceRes.code === 0) sourceOptions.value = sourceRes.data
    if (paymentRes.code === 0) paymentOptions.value = paymentRes.data
    if (carrierRes.code === 0) carrierOptions.value = carrierRes.data
    if (shipStatusRes.code === 0) shipmentStatusOptions.value = shipStatusRes.data
  } catch (error) {
    console.error('获取选项失败:', error)
  }
}

async function fetchOrderDetail() {
  if (!props.order?.id) return

  try {
    const res = await getMoqOrder(props.order.id)
    if (res.code === 0) {
      orderDetail.value = res.data
    }
  } catch (error) {
    console.error('获取订单详情失败:', error)
  }
}

function handleClose() {
  emit('update:modelValue', false)
}

async function handleConfirm() {
  try {
    await ElMessageBox.confirm('确定要确认此订单吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const res = await confirmMoqOrder(props.order.id)
    if (res.code === 0) {
      ElMessage.success('确认成功')
      fetchOrderDetail()
      emit('refresh')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('确认订单失败:', error)
    }
  }
}

function handleShip() {
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

    const items = orderDetail.value.items?.map(item => ({
      order_item_id: item.id,
      quantity: item.quantity - item.shipped_quantity
    })) || []

    const res = await shipMoqOrder(props.order.id, {
      ...shipForm,
      items
    })

    if (res.code === 0) {
      ElMessage.success('发货成功')
      shipDialogVisible.value = false
      fetchOrderDetail()
      emit('refresh')
    }
  } catch (error) {
    console.error('发货失败:', error)
  } finally {
    shipLoading.value = false
  }
}

async function handleComplete() {
  try {
    await ElMessageBox.confirm('确定要完成此订单吗？完成后将无法撤销。', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const res = await completeMoqOrder(props.order.id)
    if (res.code === 0) {
      ElMessage.success('订单已完成')
      fetchOrderDetail()
      emit('refresh')
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('完成订单失败:', error)
    }
  }
}

function handleCancel() {
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
    const res = await cancelMoqOrder(props.order.id, {
      reason: cancelReason.value
    })

    if (res.code === 0) {
      ElMessage.success('取消成功')
      cancelDialogVisible.value = false
      fetchOrderDetail()
      emit('refresh')
    }
  } catch (error) {
    console.error('取消订单失败:', error)
  } finally {
    cancelLoading.value = false
  }
}
</script>

<style scoped lang="scss">
.order-detail {
  .detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #ebeef5;

    .order-status {
      display: flex;
      align-items: center;
      gap: 12px;

      .order-no {
        font-size: 18px;
        font-weight: 600;
        color: #303133;
      }
    }
  }

  .section-title {
    margin: 20px 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    padding-left: 8px;
    border-left: 3px solid #409eff;
  }

  .info-section {
    margin-bottom: 12px;
  }

  .items-table {
    margin-bottom: 12px;
  }

  .amount-section {
    background: #f5f7fa;
    padding: 16px;
    border-radius: 6px;

    .amount-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;

      &:last-child {
        margin-bottom: 0;
      }

      .label {
        color: #606266;
      }

      .value {
        color: #303133;
        font-weight: 500;

        &.discount {
          color: #67c23a;
        }

        &.paid {
          color: #67c23a;
        }

        &.unpaid {
          color: #f56c6c;
        }
      }

      &.total {
        padding-top: 10px;
        border-top: 1px dashed #dcdfe6;

        .value {
          font-size: 18px;
          font-weight: 600;
          color: #f56c6c;
        }
      }
    }
  }

  .shipment-card {
    background: #f5f7fa;
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 10px;

    .shipment-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;

      .shipment-no {
        font-weight: 500;
        color: #303133;
      }
    }

    .shipment-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 13px;
      color: #606266;
    }
  }
}
</style>
