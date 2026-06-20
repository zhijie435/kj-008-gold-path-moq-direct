import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/moq-orders'
  },
  {
    path: '/moq-orders',
    name: 'MoqOrders',
    component: () => import('@/views/MoqOrder/index.vue'),
    meta: { title: 'MOQ订单管理' }
  },
  {
    path: '/shipments',
    name: 'Shipments',
    component: () => import('@/views/Shipment/index.vue'),
    meta: { title: '发货管理' }
  },
  {
    path: '/products',
    name: 'Products',
    component: () => import('@/views/Product/index.vue'),
    meta: { title: '商品管理' }
  },
  {
    path: '/suppliers',
    name: 'Suppliers',
    component: () => import('@/views/Supplier/index.vue'),
    meta: { title: '供应商管理' }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound/index.vue')
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - MOQ直发管理系统` : 'MOQ直发管理系统'
  next()
})

export default router
