<template>
  <el-container class="app-container">
    <el-aside width="220px" class="app-aside">
      <div class="logo">
        <el-icon size="28" color="#fff"><Goods /></el-icon>
        <span>MOQ直发管理</span>
      </div>
      <el-menu
        :default-active="activeMenu"
        router
        background-color="#001529"
        text-color="#c9d1d9"
        active-text-color="#ffffff"
        class="app-menu"
      >
        <el-menu-item index="/moq-orders">
          <el-icon><Document /></el-icon>
          <span>MOQ订单</span>
        </el-menu-item>
        <el-menu-item index="/shipments">
          <el-icon><Van /></el-icon>
          <span>发货管理</span>
        </el-menu-item>
        <el-menu-item index="/products">
          <el-icon><Goods /></el-icon>
          <span>商品管理</span>
        </el-menu-item>
        <el-menu-item index="/suppliers">
          <el-icon><OfficeBuilding /></el-icon>
          <span>供应商管理</span>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="app-header">
        <div class="header-left">
          <el-icon size="20" color="#606266"><Menu /></el-icon>
          <el-breadcrumb separator="/" class="app-breadcrumb">
            <el-breadcrumb-item :to="{ path: activeMenu }">{{ currentPageTitle }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <el-dropdown>
            <span class="user-info">
              <el-avatar :size="32" style="background: #409eff">A</el-avatar>
              <span class="username">管理员</span>
              <el-icon><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item>个人中心</el-dropdown-item>
                <el-dropdown-item divided>退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="app-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const activeMenu = computed(() => route.path)

const pageTitleMap = {
  '/moq-orders': 'MOQ订单管理',
  '/shipments': '发货管理',
  '/products': '商品管理',
  '/suppliers': '供应商管理'
}

const currentPageTitle = computed(() => pageTitleMap[route.path] || '首页')
</script>

<style scoped lang="scss">
.app-container {
  height: 100vh;
}

.app-aside {
  background: #001529;
  overflow: hidden;

  .logo {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    background: #002140;
    border-bottom: 1px solid #002140;
  }

  .app-menu {
    border-right: none;
    padding-top: 8px;
  }
}

.app-header {
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;

  .header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .app-breadcrumb {
    font-size: 14px;
  }

  .header-right {
    .user-info {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      color: #606266;

      .username {
        font-size: 14px;
      }
    }
  }
}

.app-main {
  background: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
}
</style>
