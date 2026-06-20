<template>
  <div class="shipment-list">
    <el-card class="filter-card" shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="发货单号/物流单号"
            clearable
            style="width: 240px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="物流状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="物流公司">
          <el-select v-model="queryParams.carrier_code" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in carrierOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="发货时间">
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
        <el-table-column prop="shipment_no" label="发货单号" width="180">
          <template #default="{ row }">
            <span class="shipment-no" @click="handleView(row)">{{ row.shipment_no }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="order.order_no" label="关联订单" width="180">
          <template #default="{ row }">
            {{ row.order?.order_no || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="carrier_name" label="物流公司" width="120">
          <template #default="{ row }">
            {{ row.carrier_name || getCarrierLabel(row.carrier_code) }}
          </template>
        </el-table-column>
        <el-table-column prop="tracking_no" label="物流单号" width="180">
          <template #default="{ row }">
            <el-tooltip content="点击复制" placement="top">
              <span class="tracking-no" @click="copyTrackingNo(row.tracking_no)">
                {{ row.tracking_no }}
                <el-icon><CopyDocument /></el-icon>
              </span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="物流状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="shipping_cost" label="运费" width="100" align="right">
          <template #default="{ row }">
            ¥{{ formatMoney(row.shipping_cost) }}
          </template>
        </el-table-column>
        <el-table-column prop="weight" label="重量" width="100" align="right">
          <template #default="{ row }">
            {{ row.weight }}kg
          </template>
        </el-table-column>
        <el-table-column prop="shipped_at" label="发货时间" width="170" />
        <el-table-column prop="delivered_at" label="签收时间" width="170">
          <template #default="{ row }">
            {{ row.delivered_at || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="handleView(row)">
              详情
            </el-button>
            <el-button type="primary" link size="small" @click="handleTracking(row)">
              轨迹
            </el-button>
            <el-button type="warning" link size="small" @click="handleUpdate(row)">
              更新
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

    <el-drawer v-model="detailVisible" title="物流详情" size="500px">
      <div v-if="currentShipment" class="shipment-detail">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="发货单号">{{ currentShipment.shipment_no }}</el-descriptions-item>
          <el-descriptions-item label="关联订单">{{ currentShipment.order?.order_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="物流公司">
            {{ currentShipment.carrier_name || getCarrierLabel(currentShipment.carrier_code) }}
          </el-descriptions-item>
          <el-descriptions-item label="物流单号">{{ currentShipment.tracking_no }}</el-descriptions-item>
          <el-descriptions-item label="物流状态">
            <el-tag :type="getStatusType(currentShipment.status)">
              {{ getStatusLabel(currentShipment.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="运费">¥{{ formatMoney(currentShipment.shipping_cost) }}</el-descriptions-item>
          <el-descriptions-item label="重量">{{ currentShipment.weight }}kg</el-descriptions-item>
          <el-descriptions-item label="包裹数">{{ currentShipment.package_count }}个</el-descriptions-item>
          <el-descriptions-item label="发货时间">{{ currentShipment.shipped_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="签收时间">{{ currentShipment.delivered_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ currentShipment.remark || '-' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="currentShipment.tracking_data" class="tracking-section">
          <h4>物流轨迹</h4>
          <el-timeline>
            <el-timeline-item
              v-for="(item, index) in trackingList"
              :key="index"
              :timestamp="item.time"
              :type="index === 0 ? 'primary' : ''"
            >
              {{ item.content }}
            </el-timeline-item>
          </el-timeline>
        </div>
      </div>
    </el-drawer>

    <el-dialog
      v-model="updateDialogVisible"
      title="更新物流信息"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form label-width="100px">
        <el-form-item label="物流公司">
          <el-select v-model="updateForm.carrier_code" placeholder="请选择" style="width: 100%">
            <el-option
              v-for="item in carrierOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="物流单号">
          <el-input v-model="updateForm.tracking_no" placeholder="请输入物流单号" />
        </el-form-item>
        <el-form-item label="物流状态">
          <el-select v-model="updateForm.status" placeholder="请选择" style="width: 100%">
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="updateForm.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="updateDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleUpdateSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, CopyDocument } from '@element-plus/icons-vue'
import {
  getShipments,
  getShipment,
  updateShipment,
  getShipmentStatusOptions,
  getCarrierOptions
} from '@/api/moqDirectShip'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const statusOptions = ref([])
const carrierOptions = ref([])
const detailVisible = ref(false)
const currentShipment = ref(null)
const updateDialogVisible = ref(false)
const currentUpdateShipment = ref(null)

const queryParams = reactive({
  page: 1,
  per_page: 15,
  keyword: '',
  status: '',
  carrier_code: '',
  start_date: '',
  end_date: ''
})

const dateRange = ref([])

const updateForm = reactive({
  carrier_code: '',
  tracking_no: '',
  status: '',
  remark: ''
})

const trackingList = computed(() => {
  if (!currentShipment.value?.tracking_data) return []
  const data = currentShipment.value.tracking_data
  if (Array.isArray(data)) return data
  if (data.list) return data.list
  return []
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
    picked: 'primary',
    shipped: '',
    in_transit: 'info',
    delivered: 'success',
    failed: 'danger',
    returned: 'danger'
  }
  return typeMap[status] || 'info'
}

function getCarrierLabel(code) {
  const item = carrierOptions.value.find(c => c.value === code)
  return item ? item.label : code
}

function copyTrackingNo(trackingNo) {
  navigator.clipboard.writeText(trackingNo)
  ElMessage.success('已复制到剪贴板')
}

async function fetchStatusOptions() {
  try {
    const res = await getShipmentStatusOptions()
    if (res.code === 0) {
      statusOptions.value = res.data
    }
  } catch (error) {
    console.error('获取状态选项失败:', error)
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

    const res = await getShipments(params)
    if (res.code === 0) {
      tableData.value = res.data.list
      total.value = res.data.total
    }
  } catch (error) {
    console.error('获取物流列表失败:', error)
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
  dateRange.value = []
  queryParams.page = 1
  fetchData()
}

async function handleView(row) {
  try {
    const res = await getShipment(row.id)
    if (res.code === 0) {
      currentShipment.value = res.data
      detailVisible.value = true
    }
  } catch (error) {
    console.error('获取物流详情失败:', error)
  }
}

function handleTracking(row) {
  handleView(row)
}

function handleUpdate(row) {
  currentUpdateShipment.value = row
  Object.assign(updateForm, {
    carrier_code: row.carrier_code,
    tracking_no: row.tracking_no,
    status: row.status,
    remark: row.remark || ''
  })
  updateDialogVisible.value = true
}

async function handleUpdateSubmit() {
  try {
    const res = await updateShipment(currentUpdateShipment.value.id, updateForm)
    if (res.code === 0) {
      ElMessage.success('更新成功')
      updateDialogVisible.value = false
      fetchData()
    }
  } catch (error) {
    console.error('更新失败:', error)
  }
}

onMounted(() => {
  fetchStatusOptions()
  fetchCarrierOptions()
  fetchData()
})
</script>

<style scoped lang="scss">
.shipment-list {
  padding: 20px 0;

  .filter-card {
    margin-bottom: 20px;
  }

  .shipment-no {
    color: #409eff;
    cursor: pointer;
    font-weight: 500;
  }

  .tracking-no {
    color: #606266;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;

    &:hover {
      color: #409eff;
    }
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }

  .shipment-detail {
    .tracking-section {
      margin-top: 20px;

      h4 {
        margin: 0 0 16px 0;
        font-size: 14px;
        color: #303133;
      }
    }
  }
}
</style>
