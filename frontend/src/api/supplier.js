import request from '@/utils/request'

const BASE_URL = '/api/v1/suppliers'

export function getSuppliers(params) {
  return request({
    url: BASE_URL,
    method: 'get',
    params
  })
}

export function getActiveSuppliers() {
  return request({
    url: `${BASE_URL}/all-active`,
    method: 'get'
  })
}

export function getSupplier(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'get'
  })
}

export function createSupplier(data) {
  return request({
    url: BASE_URL,
    method: 'post',
    data
  })
}

export function updateSupplier(id, data) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'put',
    data
  })
}

export function deleteSupplier(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'delete'
  })
}

export function toggleSupplierActive(id) {
  return request({
    url: `${BASE_URL}/${id}/toggle-active`,
    method: 'post'
  })
}

export function getSupplierStatusOptions() {
  return request({
    url: `${BASE_URL}/status-options`,
    method: 'get'
  })
}
