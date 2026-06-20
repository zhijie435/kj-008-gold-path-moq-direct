<template>
  <div class="moq-direct-ship-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">国内小批量 MOQ 直发</h2>
        <p class="page-desc">管理国内小批量订单，支持MOQ最小起订量，供应商直发客户</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleCreateOrder">
          <el-icon><Plus /></el-icon>新建订单
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
            <div class="stat-value">{{ stats.orders?.total || 0 }}</div>
            <div class="stat-label">今日订单</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon success">
            <el-icon><Money /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">¥{{ formatMoney(stats.orders?.total_amount) }}</div>
            <div class="stat-label">订单金额</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon warning">
            <el-icon><Warning /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.orders?.pending || 0 }}</div>
            <div class="stat-label">待处理</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-icon info">
            <el-icon><Van /></el-icon>
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.shipments?.delivered || 0 }}</div>
            <div class="stat-label">已签收</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" class="main-tabs">
      <el-tab-pane label="订单管理" name="orders">
        <OrderList
          v-if="activeTab === 'orders'"
          @create="handleCreateOrder"
          @view="handleViewOrder"
          @refresh="fetchStats"
        />
      </el-tab-pane>
      <el-tab-pane label="产品管理" name="products">
        <ProductList v-if="activeTab === 'products'" />
      </el-tab-pane>
      <el-tab-pane label="供应商管理" name="suppliers">
        <SupplierList v-if="activeTab === 'suppliers'" />
      </el-tab-pane>
      <el-tab-pane label="物流跟踪" name="shipments">
        <ShipmentList v-if="activeTab === 'shipments'" />
      </el-tab-pane>
    </el-tabs>

    <OrderDetail
      v-model="detailVisible"
      :order="currentOrder"
      @refresh="fetchStats"
    />

    <OrderForm
      v-model="orderFormVisible"
      :is-edit="false"
      :order-data="null"
      @success="handleOrderSuccess"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Document, Money, Warning, Van } from '@element-plus/icons-vue'
import { getMoqOrderStatistics } from '@/api/moqDirectShip'
import OrderList from './components/OrderList.vue'
import ProductList from './components/ProductList.vue'
import SupplierList from './components/SupplierList.vue'
import ShipmentList from './components/ShipmentList.vue'
import OrderDetail from './components/OrderDetail.vue'
import OrderForm from './components/OrderForm.vue'

const activeTab = ref('orders')
const stats = ref({})
const detailVisible = ref(false)
const orderFormVisible = ref(false)
const currentOrder = ref(null)

function formatMoney(value) {
  if (!value && value !== 0) return '0.00'
  return Number(value).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

async function fetchStats() {
  try {
    const res = await getMoqOrderStatistics()
    if (res.code === 0) {
      stats.value = res.data
    }
  } catch (error) {
    console.error('获取统计数据失败:', error)
  }
}

function handleCreateOrder() {
  orderFormVisible.value = true
}

function handleViewOrder(order) {
  currentOrder.value = order
  detailVisible.value = true
}

function handleOrderSuccess() {
  orderFormVisible.value = false
  fetchStats()
  ElMessage.success('创建成功')
}

onMounted(() => {
  fetchStats()
})
</script>

<style scoped lang="scss">
.moq-direct-ship-page {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 20px;

    .page-title {
      font-size: 20px;
      color: #303133;
      margin: 0 0 4px 0;
      font-weight: 600;
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
    gap: 16px;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: #fff;

      &.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
      &.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
      &.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
      &.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    }

    .stat-content {
      .stat-value {
        font-size: 24px;
        font-weight: 600;
        color: #303133;
        line-height: 1.2;
      }

      .stat-label {
        font-size: 13px;
        color: #909399;
        margin-top: 4px;
      }
    }
  }

  .main-tabs {
    background: #fff;
    border-radius: 8px;
    padding: 0 20px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);

    :deep(.el-tabs__header) {
      margin: 0;
    }
  }
}
</style>
