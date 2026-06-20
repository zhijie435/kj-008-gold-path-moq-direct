import request from '@/utils/request'

const BASE_URL = '/api/v1/moq-orders'

export function getMoqOrders(params) {
  return request({
    url: BASE_URL,
    method: 'get',
    params
  })
}

export function getMoqOrder(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'get'
  })
}

export function createMoqOrder(data) {
  return request({
    url: BASE_URL,
    method: 'post',
    data
  })
}

export function updateMoqOrder(id, data) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'put',
    data
  })
}

export function deleteMoqOrder(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'delete'
  })
}

export function confirmMoqOrder(id) {
  return request({
    url: `${BASE_URL}/${id}/confirm`,
    method: 'post'
  })
}

export function cancelMoqOrder(id, reason) {
  return request({
    url: `${BASE_URL}/${id}/cancel`,
    method: 'post',
    data: { reason }
  })
}

export function startProcessingMoqOrder(id) {
  return request({
    url: `${BASE_URL}/${id}/start-processing`,
    method: 'post'
  })
}

export function completeMoqOrder(id) {
  return request({
    url: `${BASE_URL}/${id}/complete`,
    method: 'post'
  })
}

export function updateMoqOrderPayment(id, data) {
  return request({
    url: `${BASE_URL}/${id}/update-payment`,
    method: 'post',
    data
  })
}

export function getMoqOrderStatusOptions() {
  return request({
    url: `${BASE_URL}/status-options`,
    method: 'get'
  })
}

export function getMoqOrderSourceOptions() {
  return request({
    url: `${BASE_URL}/source-options`,
    method: 'get'
  })
}

export function getMoqOrderPaymentOptions() {
  return request({
    url: `${BASE_URL}/payment-options`,
    method: 'get'
  })
}
