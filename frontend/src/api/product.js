import request from '@/utils/request'

const BASE_URL = '/api/v1/products'

export function getProducts(params) {
  return request({
    url: BASE_URL,
    method: 'get',
    params
  })
}

export function getProduct(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'get'
  })
}

export function createProduct(data) {
  return request({
    url: BASE_URL,
    method: 'post',
    data
  })
}

export function updateProduct(id, data) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'put',
    data
  })
}

export function deleteProduct(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'delete'
  })
}

export function toggleProductActive(id) {
  return request({
    url: `${BASE_URL}/${id}/toggle-active`,
    method: 'post'
  })
}

export function updateProductStock(id, data) {
  return request({
    url: `${BASE_URL}/${id}/update-stock`,
    method: 'post',
    data
  })
}

export function getProductUnitOptions() {
  return request({
    url: `${BASE_URL}/unit-options`,
    method: 'get'
  })
}

export function getProductStatusOptions() {
  return request({
    url: `${BASE_URL}/status-options`,
    method: 'get'
  })
}
