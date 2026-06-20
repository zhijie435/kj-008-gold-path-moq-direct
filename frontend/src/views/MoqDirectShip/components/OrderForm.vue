<template>
  <el-dialog
    :model-value="modelValue"
    @update:model-value="handleClose"
    :title="isEdit ? '编辑订单' : '新建订单'"
    width="800px"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
      <h4 class="form-section-title">基本信息</h4>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="供应商" prop="supplier_id">
            <el-select
              v-model="formData.supplier_id"
              placeholder="请选择供应商"
              style="width: 100%"
              @change="handleSupplierChange"
            >
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
          <el-form-item label="订单来源" prop="source">
            <el-select v-model="formData.source" placeholder="请选择" style="width: 100%">
              <el-option
                v-for="item in sourceOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section-title">收货信息</h4>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="客户姓名" prop="customer_name">
            <el-input v-model="formData.customer_name" placeholder="请输入客户姓名" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="联系电话" prop="customer_phone">
            <el-input v-model="formData.customer_phone" placeholder="请输入联系电话" />
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
        <el-input v-model="formData.address" type="textarea" :rows="2" placeholder="请输入详细地址" />
      </el-form-item>
      <el-form-item label="地址备注">
        <el-input v-model="formData.address_detail" placeholder="如：门牌号、楼层等" />
      </el-form-item>

      <h4 class="form-section-title">
        商品列表
        <el-button type="primary" link size="small" @click="handleAddProduct">
          <el-icon><Plus /></el-icon>添加商品
        </el-button>
      </h4>
      <el-table :data="formData.items" border class="products-table">
        <el-table-column prop="product_name" label="商品名称" min-width="180">
          <template #default="{ row, $index }">
            <el-select
              v-model="row.product_id"
              placeholder="选择商品"
              filterable
              style="width: 100%"
              @change="(val) => handleProductChange(val, $index)"
            >
              <el-option
                v-for="item in productOptions"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column prop="product_sku" label="SKU" width="120" />
        <el-table-column prop="specification" label="规格" width="120">
          <template #default="{ row }">
            {{ row.specification || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="单价(元)" width="120">
          <template #default="{ row }">
            <el-input-number
              v-model="row.unit_price"
              :min="0"
              :precision="2"
              :controls="false"
              style="width: 100%"
              @change="calculateItemTotal(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="MOQ" width="80" align="center">
          <template #default="{ row }">
            {{ row.moq || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="数量" width="120">
          <template #default="{ row }">
            <el-input-number
              v-model="row.quantity"
              :min="1"
              :controls="false"
              style="width: 100%"
              @change="calculateItemTotal(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="小计" width="120" align="right">
          <template #default="{ row }">
            <span class="item-total">¥{{ formatMoney(row.total_price || 0) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="60" align="center">
          <template #default="{ $index }">
            <el-button type="danger" link @click="handleRemoveProduct($index)">
              <el-icon><Delete /></el-icon>
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <h4 class="form-section-title">费用信息</h4>
      <el-row :gutter="20">
        <el-col :span="8">
          <el-form-item label="运费">
            <el-input-number v-model="formData.shipping_fee" :min="0" :precision="2" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="优惠金额">
            <el-input-number v-model="formData.discount_amount" :min="0" :precision="2" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="应付总额">
            <div class="total-amount">¥{{ formatMoney(totalAmount) }}</div>
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section-title">支付信息</h4>
      <el-row :gutter="20">
        <el-col :span="12">
          <el-form-item label="支付方式">
            <el-select v-model="formData.payment_method" placeholder="请选择" style="width: 100%">
              <el-option
                v-for="item in paymentOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="已付金额">
            <el-input-number v-model="formData.paid_amount" :min="0" :precision="2" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <h4 class="form-section-title">备注信息</h4>
      <el-form-item label="客户备注">
        <el-input v-model="formData.remark" type="textarea" :rows="2" placeholder="客户备注信息" />
      </el-form-item>
      <el-form-item label="内部备注">
        <el-input v-model="formData.internal_note" type="textarea" :rows="2" placeholder="内部备注，客户不可见" />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
        {{ isEdit ? '保存修改' : '创建订单' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Delete } from '@element-plus/icons-vue'
import {
  createMoqOrder,
  updateMoqOrder,
  getAllSuppliers,
  getProducts,
  getOrderSourceOptions,
  getPaymentOptions
} from '@/api/moqDirectShip'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  isEdit: {
    type: Boolean,
    default: false
  },
  orderData: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'success'])

const formRef = ref(null)
const submitLoading = ref(false)
const supplierOptions = ref([])
const productOptions = ref([])
const sourceOptions = ref([])
const paymentOptions = ref([])

const formData = reactive({
  id: null,
  supplier_id: '',
  customer_name: '',
  customer_phone: '',
  province: '',
  city: '',
  district: '',
  address: '',
  address_detail: '',
  shipping_fee: 0,
  discount_amount: 0,
  paid_amount: 0,
  payment_method: '',
  source: 'manual',
  remark: '',
  internal_note: '',
  items: []
})

const formRules = {
  supplier_id: [{ required: true, message: '请选择供应商', trigger: 'change' }],
  customer_name: [{ required: true, message: '请输入客户姓名', trigger: 'blur' }],
  customer_phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  province: [{ required: true, message: '请输入省份', trigger: 'blur' }],
  city: [{ required: true, message: '请输入城市', trigger: 'blur' }],
  district: [{ required: true, message: '请输入区县', trigger: 'blur' }],
  address: [{ required: true, message: '请输入详细地址', trigger: 'blur' }]
}

const totalAmount = computed(() => {
  const itemsTotal = formData.items.reduce((sum, item) => {
    return sum + (item.total_price || 0)
  }, 0)
  return itemsTotal + (formData.shipping_fee || 0) - (formData.discount_amount || 0)
})

watch(() => props.modelValue, (val) => {
  if (val) {
    fetchOptions()
    if (props.isEdit && props.orderData) {
      Object.assign(formData, { ...props.orderData })
    } else {
      resetForm()
    }
  }
})

function formatMoney(value) {
  if (!value && value !== 0) return '0.00'
  return Number(value).toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

async function fetchOptions() {
  try {
    const [supplierRes, sourceRes, paymentRes] = await Promise.all([
      getAllSuppliers(),
      getOrderSourceOptions(),
      getPaymentOptions()
    ])

    if (supplierRes.code === 0) supplierOptions.value = supplierRes.data
    if (sourceRes.code === 0) sourceOptions.value = sourceRes.data
    if (paymentRes.code === 0) paymentOptions.value = paymentRes.data
  } catch (error) {
    console.error('获取选项失败:', error)
  }
}

async function fetchProducts(supplierId) {
  if (!supplierId) {
    productOptions.value = []
    return
  }

  try {
    const res = await getProducts({ supplier_id: supplierId, is_active: true, per_page: 100 })
    if (res.code === 0) {
      productOptions.value = res.data.list
    }
  } catch (error) {
    console.error('获取产品列表失败:', error)
  }
}

function handleSupplierChange(val) {
  fetchProducts(val)
  formData.items = []
}

function handleAddProduct() {
  formData.items.push({
    product_id: '',
    product_name: '',
    product_sku: '',
    specification: '',
    unit_price: 0,
    quantity: 1,
    total_price: 0,
    moq: 0,
    remark: ''
  })
}

function handleRemoveProduct(index) {
  formData.items.splice(index, 1)
}

function handleProductChange(productId, index) {
  const product = productOptions.value.find(p => p.id === productId)
  if (product) {
    const item = formData.items[index]
    item.product_name = product.name
    item.product_sku = product.sku
    item.specification = product.specification
    item.unit_price = product.price
    item.moq = product.moq
    if (item.quantity < product.moq) {
      item.quantity = product.moq
    }
    calculateItemTotal(item)
  }
}

function calculateItemTotal(row) {
  row.total_price = (row.unit_price || 0) * (row.quantity || 0)
}

function resetForm() {
  Object.assign(formData, {
    id: null,
    supplier_id: '',
    customer_name: '',
    customer_phone: '',
    province: '',
    city: '',
    district: '',
    address: '',
    address_detail: '',
    shipping_fee: 0,
    discount_amount: 0,
    paid_amount: 0,
    payment_method: '',
    source: 'manual',
    remark: '',
    internal_note: '',
    items: []
  })
}

function validateMoq() {
  for (const item of formData.items) {
    if (item.moq && item.quantity < item.moq) {
      ElMessage.error(`产品 "${item.product_name}" 最小起订量为 ${item.moq} 件`)
      return false
    }
  }
  return true
}

function handleClose() {
  emit('update:modelValue', false)
}

async function handleSubmit() {
  if (!formRef.value) return

  if (formData.items.length === 0) {
    ElMessage.warning('请至少添加一个商品')
    return
  }

  if (!validateMoq()) {
    return
  }

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (props.isEdit) {
      // TODO: update order
    } else {
      const res = await createMoqOrder(formData)
      if (res.code === 0) {
        ElMessage.success('创建成功')
        emit('success')
        handleClose()
      } else {
        ElMessage.error(res.message || '创建失败')
      }
    }
  } catch (error) {
    console.error('提交失败:', error)
  } finally {
    submitLoading.value = false
  }
}
</script>

<style scoped lang="scss">
.form-section-title {
  margin: 20px 0 12px 0;
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  padding-left: 8px;
  border-left: 3px solid #409eff;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.products-table {
  margin-bottom: 12px;

  .item-total {
    color: #f56c6c;
    font-weight: 500;
  }
}

.total-amount {
  font-size: 18px;
  font-weight: 600;
  color: #f56c6c;
}
</style>
