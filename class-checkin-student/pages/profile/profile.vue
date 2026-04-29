<template>
  <view class="container">
    <!-- 用户信息卡片 -->
    <view class="user-card">
      <view class="avatar">👤</view>
      <view class="user-info">
        <text class="name">{{ user.name || '未知' }}</text>
        <text class="student-id">学号：{{ user.student_id || '未知' }}</text>
      </view>
    </view>

    <!-- 统计卡片 -->
    <view class="stats-card">
      <view class="stat-item">
        <text class="stat-number">{{ stats.total_checkins || 0 }}</text>
        <text class="stat-label">总签到</text>
      </view>
    </view>

    <!-- 功能菜单 -->
    <view class="menu-list">
      <view class="menu-item" @click="goToHistory">
        <text class="menu-icon">📋</text>
        <text class="menu-text">签到记录</text>
        <text class="menu-arrow">›</text>
      </view>
      <view class="menu-item" @click="showChangePwd = true">
        <text class="menu-icon">🔐</text>
        <text class="menu-text">修改密码</text>
        <text class="menu-arrow">›</text>
      </view>
      <view class="menu-item" @click="logout">
        <text class="menu-icon">🚪</text>
        <text class="menu-text">退出登录</text>
        <text class="menu-arrow">›</text>
      </view>
    </view>

    <!-- 修改密码弹窗 -->
    <view class="modal" v-if="showChangePwd" @click="showChangePwd = false">
      <view class="modal-content" @click.stop>
        <view class="modal-title">修改密码</view>
        <input type="password" v-model="oldPassword" placeholder="原密码" class="modal-input" />
        <input type="password" v-model="newPassword" placeholder="新密码（至少6位）" class="modal-input" />
        <input type="password" v-model="confirmPassword" placeholder="确认新密码" class="modal-input" />
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="showChangePwd = false">取消</button>
          <button class="modal-btn confirm" @click="changePassword">确认</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      user: {},
      stats: { total_checkins: 0 },
      showChangePwd: false,
      oldPassword: '',
      newPassword: '',
      confirmPassword: ''
    }
  },
  onShow() {
    this.user = uni.getStorageSync('user') || {}
    this.fetchStats()
  },
  methods: {
    fetchStats() {
      const token = uni.getStorageSync('token')
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_user_stats', token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          if (res.data.code === 200) {
            this.stats = res.data.data
          }
        }
      })
    },
    goToHistory() {
      uni.navigateTo({ url: '/pages/history/history' })
    },
    changePassword() {
      if (!this.oldPassword || !this.newPassword) {
        uni.showToast({ title: '请填写完整', icon: 'none' })
        return
      }
      if (this.newPassword.length < 6) {
        uni.showToast({ title: '密码至少6位', icon: 'none' })
        return
      }
      if (this.newPassword !== this.confirmPassword) {
        uni.showToast({ title: '两次密码不一致', icon: 'none' })
        return
      }

      const token = uni.getStorageSync('token')
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'change_password', old_password: this.oldPassword, new_password: this.newPassword, token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          if (res.data.code === 200) {
            uni.showToast({ title: '修改成功', icon: 'success' })
            this.showChangePwd = false
            this.oldPassword = ''
            this.newPassword = ''
            this.confirmPassword = ''
          } else {
            uni.showToast({ title: res.data.msg, icon: 'none' })
          }
        }
      })
    },
    logout() {
      uni.showModal({
        title: '提示',
        content: '确定要退出登录吗？',
        success: (res) => {
          if (res.confirm) {
            uni.removeStorageSync('token')
            uni.removeStorageSync('user')
            uni.reLaunch({ url: '/pages/login/login' })
          }
        }
      })
    }
  }
}
</script>

<style scoped>
.container {
  min-height: 100vh;
  background-color: #f5f7fa;
  padding: 30rpx;
}
.user-card {
  background: white;
  border-radius: 24rpx;
  padding: 40rpx;
  margin-bottom: 30rpx;
  display: flex;
  align-items: center;
  box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
}
.avatar {
  width: 120rpx;
  height: 120rpx;
  border-radius: 60rpx;
  background: #007aff;
  color: white;
  font-size: 60rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 30rpx;
}
.user-info {
  flex: 1;
}
.name {
  display: block;
  font-size: 40rpx;
  font-weight: bold;
  color: #212529;
  margin-bottom: 10rpx;
}
.student-id {
  font-size: 28rpx;
  color: #6c757d;
}
.stats-card {
  background: white;
  border-radius: 24rpx;
  padding: 30rpx;
  margin-bottom: 30rpx;
  box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
}
.stat-item {
  text-align: center;
}
.stat-number {
  display: block;
  font-size: 48rpx;
  font-weight: bold;
  color: #007aff;
  margin-bottom: 10rpx;
}
.stat-label {
  font-size: 26rpx;
  color: #868e96;
}
.menu-list {
  background: white;
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
}
.menu-item {
  display: flex;
  align-items: center;
  padding: 30rpx;
  border-bottom: 1rpx solid #f1f3f5;
}
.menu-item:last-child {
  border-bottom: none;
}
.menu-icon {
  width: 60rpx;
  font-size: 40rpx;
  margin-right: 20rpx;
}
.menu-text {
  flex: 1;
  font-size: 32rpx;
  color: #212529;
}
.menu-arrow {
  font-size: 40rpx;
  color: #adb5bd;
}
.modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-content {
  background: white;
  border-radius: 30rpx;
  padding: 40rpx;
  width: 600rpx;
}
.modal-title {
  font-size: 36rpx;
  font-weight: bold;
  text-align: center;
  margin-bottom: 30rpx;
}
.modal-input {
  background: #f5f5f5;
  border: 2rpx solid #e9ecef;
  padding: 25rpx;
  border-radius: 16rpx;
  font-size: 30rpx;
  margin-bottom: 20rpx;
}
.modal-buttons {
  display: flex;
  gap: 20rpx;
  margin-top: 20rpx;
}
.modal-btn {
  flex: 1;
  height: 80rpx;
  line-height: 80rpx;
  border-radius: 40rpx;
  font-size: 32rpx;
  border: none;
}
.modal-btn.cancel {
  background: #f8f9fa;
  color: #6c757d;
}
.modal-btn.confirm {
  background: #007aff;
  color: white;
}
</style>