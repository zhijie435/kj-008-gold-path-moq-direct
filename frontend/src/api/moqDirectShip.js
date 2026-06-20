import request from '@/utils/request'

export function getMoqOrders(params) {
  return request({
    url: '/v1/moq-direct-ship/orders',
    method: 'get',
    params
  })
}

export function getMoqOrder(id) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}`,
    method: 'get'
  })
}

export function createMoqOrder(data) {
  return request({
    url: '/v1/moq-direct-ship/orders',
    method: 'post',
    data
  })
}

export function confirmMoqOrder(id) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/confirm`,
    method: 'post'
  })
}

export function processMoqOrder(id) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/process`,
    method: 'post'
  })
}

export function shipMoqOrder(id, data) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/ship`,
    method: 'post',
    data
  })
}

export function completeMoqOrder(id) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/complete`,
    method: 'post'
  })
}

export function cancelMoqOrder(id, data) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/cancel`,
    method: 'post',
    data
  })
}

export function refundMoqOrder(id, data) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/refund`,
    method: 'post',
    data
  })
}

export function payMoqOrder(id, data) {
  return request({
    url: `/v1/moq-direct-ship/orders/${id}/pay`,
    method: 'post',
    data
  })
}

export function getMoqOrderStatistics(params) {
  return request({
    url: '/v1/moq-direct-ship/orders/statistics',
    method: 'get',
    params
  })
}

export function getOrderStatusOptions() {
  return request({
    url: '/v1/moq-direct-ship/orders/status-options',
    method: 'get'
  })
}

export function getOrderSourceOptions() {
  return request({
    url: '/v1/moq-direct-ship/orders/source-options',
    method: 'get'
  })
}

export function getPaymentOptions() {
  return request({
    url: '/v1/moq-direct-ship/orders/payment-options',
    method: 'get'
  })
}

export function getProducts(params) {
  return request({
    url: '/v1/moq-direct-ship/products',
    method: 'get',
    params
  })
}

export function getProduct(id) {
  return request({
    url: `/v1/moq-direct-ship/products/${id}`,
    method: 'get'
  })
}

export function createProduct(data) {
  return request({
    url: '/v1/moq-direct-ship/products',
    method: 'post',
    data
  })
}

export function updateProduct(id, data) {
  return request({
    url: `/v1/moq-direct-ship/products/${id}`,
    method: 'put',
    data
  })
}

export function deleteProduct(id) {
  return request({
    url: `/v1/moq-direct-ship/products/${id}`,
    method: 'delete'
  })
}

export function toggleProductStatus(id) {
  return request({
    url: `/v1/moq-direct-ship/products/${id}/toggle-status`,
    method: 'post'
  })
}

export function updateProductStock(id, data) {
  return request({
    url: `/v1/moq-direct-ship/products/${id}/update-stock`,
    method: 'post',
    data
  })
}

export function getProductStatusOptions() {
  return request({
    url: '/v1/moq-direct-ship/products/status-options',
    method: 'get'
  })
}

export function getProductUnitOptions() {
  return request({
    url: '/v1/moq-direct-ship/products/unit-options',
    method: 'get'
  })
}

export function getProductCategories() {
  return request({
    url: '/v1/moq-direct-ship/products/categories',
    method: 'get'
  })
}

export function getSuppliers(params) {
  return request({
    url: '/v1/moq-direct-ship/suppliers',
    method: 'get',
    params
  })
}

export function getSupplier(id) {
  return request({
    url: `/v1/moq-direct-ship/suppliers/${id}`,
    method: 'get'
  })
}

export function createSupplier(data) {
  return request({
    url: '/v1/moq-direct-ship/suppliers',
    method: 'post',
    data
  })
}

export function updateSupplier(id, data) {
  return request({
    url: `/v1/moq-direct-ship/suppliers/${id}`,
    method: 'put',
    data
  })
}

export function deleteSupplier(id) {
  return request({
    url: `/v1/moq-direct-ship/suppliers/${id}`,
    method: 'delete'
  })
}

export function toggleSupplierStatus(id) {
  return request({
    url: `/v1/moq-direct-ship/suppliers/${id}/toggle-status`,
    method: 'post'
  })
}

export function getAllSuppliers() {
  return request({
    url: '/v1/moq-direct-ship/suppliers/all',
    method: 'get'
  })
}

export function getSupplierStatusOptions() {
  return request({
    url: '/v1/moq-direct-ship/suppliers/status-options',
    method: 'get'
  })
}

export function getShipments(params) {
  return request({
    url: '/v1/moq-direct-ship/shipments',
    method: 'get',
    params
  })
}

export function getShipment(id) {
  return request({
    url: `/v1/moq-direct-ship/shipments/${id}`,
    method: 'get'
  })
}

export function createShipment(data) {
  return request({
    url: '/v1/moq-direct-ship/shipments',
    method: 'post',
    data
  })
}

export function updateShipment(id, data) {
  return request({
    url: `/v1/moq-direct-ship/shipments/${id}`,
    method: 'put',
    data
  })
}

export function updateShipmentTracking(id, data) {
  return request({
    url: `/v1/moq-direct-ship/shipments/${id}/update-tracking`,
    method: 'post',
    data
  })
}

export function getShipmentStatusOptions() {
  return request({
    url: '/v1/moq-direct-ship/shipments/status-options',
    method: 'get'
  })
}

export function getCarrierOptions() {
  return request({
    url: '/v1/moq-direct-ship/shipments/carrier-options',
    method: 'get'
  })
}
