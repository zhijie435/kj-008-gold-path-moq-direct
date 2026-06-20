import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/moq-direct-ship'
  },
  {
    path: '/moq-direct-ship',
    name: 'MoqDirectShip',
    component: () => import('@/views/MoqDirectShip/index.vue'),
    meta: { title: '国内小批量MOQ直发' }
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
