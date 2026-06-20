import request from '@/utils/request'

const BASE_URL = '/api/v1/shipments'

export function getShipments(params) {
  return request({
    url: BASE_URL,
    method: 'get',
    params
  })
}

export function getShipment(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'get'
  })
}

export function createShipment(data) {
  return request({
    url: BASE_URL,
    method: 'post',
    data
  })
}

export function updateShipment(id, data) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'put',
    data
  })
}

export function deleteShipment(id) {
  return request({
    url: `${BASE_URL}/${id}`,
    method: 'delete'
  })
}

export function shipShipment(id) {
  return request({
    url: `${BASE_URL}/${id}/ship`,
    method: 'post'
  })
}

export function markShipmentPicked(id) {
  return request({
    url: `${BASE_URL}/${id}/mark-picked`,
    method: 'post'
  })
}

export function markShipmentInTransit(id) {
  return request({
    url: `${BASE_URL}/${id}/mark-in-transit`,
    method: 'post'
  })
}

export function markShipmentDelivered(id) {
  return request({
    url: `${BASE_URL}/${id}/mark-delivered`,
    method: 'post'
  })
}

export function markShipmentFailed(id, reason) {
  return request({
    url: `${BASE_URL}/${id}/mark-failed`,
    method: 'post',
    data: { reason }
  })
}

export function markShipmentReturned(id, reason) {
  return request({
    url: `${BASE_URL}/${id}/mark-returned`,
    method: 'post',
    data: { reason }
  })
}

export function getShipmentStatusOptions() {
  return request({
    url: `${BASE_URL}/status-options`,
    method: 'get'
  })
}

export function getShipmentCarrierOptions() {
  return request({
    url: `${BASE_URL}/carrier-options`,
    method: 'get'
  })
}
