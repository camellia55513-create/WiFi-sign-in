<template>
  <view class="container">
    <!-- 加载状态 -->
    <view v-if="loading" class="loading-state">
      <view class="loading-icon"></view>
      <text>加载中...</text>
    </view>

    <!-- 空状态 -->
    <view v-else-if="activities.length === 0" class="empty-state">
      <view class="empty-icon">📡</view>
      <text class="empty-text">当前没有进行中的签到活动</text>
      <text class="empty-tip">请等待教师发布热点签到</text>
    </view>

    <view v-else class="activity-list">
      <view class="activity-item" v-for="item in activities" :key="item.id">
        <view class="course-header">
          <view class="course-name">{{ item.course_name }}</view>
          <view class="course-status" :class="item.status">
            {{ getStatusText(item.status) }}
          </view>
        </view>

        <view class="course-info">
          <view class="info-row">
            <text class="label">👨‍🏫 教师：</text>
            <text class="value">{{ item.teacher_name }}</text>
          </view>
          <view class="info-row">
            <text class="label">📍 地点：</text>
            <text class="value">{{ item.location }}</text>
          </view>
          <view class="info-row">
            <text class="label">⏰ 时间：</text>
            <text class="value">{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</text>
          </view>
          <view class="info-row wifi-row">
            <text class="label">📶 WiFi：</text>
            <text class="value wifi-value">{{ item.wifi_ssid }}</text>
          </view>
          <view class="info-row" v-if="item.wifi_password">
            <text class="label">🔑 密码：</text>
            <text class="value">{{ item.wifi_password }}</text>
          </view>
        </view>

        <!-- 已签到 -->
        <view v-if="item.has_checked" class="checked-container">
          <text class="checked-tag">✅ 已签到</text>
        </view>

        <!-- 未签到且活动进行中，显示签到按钮 -->
        <view v-else-if="item.status === 'active'" class="button-group">
          <button class="checkin-btn" @click="goToCheckin(item.id)">
            立即签到
          </button>
          <!-- 人工签到按钮 -->
          <button class="manual-apply-btn" @click="goToManualApply(item.id, item.course_name)">
            人工签到
          </button>
        </view>

        <!-- 活动未开始或已结束，显示禁用按钮 -->
        <view v-else class="button-group">
          <button class="disabled-btn" disabled>
            {{ item.status === 'pending' ? '未开始' : '已结束' }}
          </button>
        </view>
      </view>
    </view>

    <!-- 人工申请弹窗 -->
    <view class="custom-modal" v-if="showManualModal" @touchmove.prevent>
      <view class="modal-content">
        <view class="modal-title">人工签到申请</view>
        <textarea v-model="manualReason" placeholder="请输入申请理由（可选）" class="modal-textarea" maxlength="200" />
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="closeManualModal">取消</button>
          <button class="modal-btn confirm" @click="submitManualApply">提交申请</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      activities: [],
      loading: false,
      timer: null,
      showManualModal: false,
      manualReason: '',
      currentActivityForManual: null
    }
  },
  onShow() {
    this.checkLoginAndFetch()
    this.startAutoRefresh()
  },
  onHide() {
    this.stopAutoRefresh()
  },
  onPullDownRefresh() {
    this.fetchActivities(true)
  },
  methods: {
    checkLoginAndFetch() {
      const token = uni.getStorageSync('token')
      const user = uni.getStorageSync('user')
      if (!token || !user) {
        uni.reLaunch({ url: '/pages/login/login' })
        return
      }
      this.fetchActivities()
    },
    startAutoRefresh() {
      this.timer = setInterval(() => {
        this.fetchActivities()
      }, 10000)
    },
    stopAutoRefresh() {
      if (this.timer) {
        clearInterval(this.timer)
        this.timer = null
      }
    },
    formatTime(timeStr) {
      if (!timeStr) return '未知'
      const date = new Date(timeStr)
      const month = date.getMonth() + 1
      const day = date.getDate()
      const hours = date.getHours().toString().padStart(2, '0')
      const minutes = date.getMinutes().toString().padStart(2, '0')
      return month + '-' + day + ' ' + hours + ':' + minutes
    },
    getStatusText(status) {
      const map = { 'pending': '待开始', 'active': '进行中', 'ended': '已结束' }
      return map[status] || status
    },
    fetchActivities(showPullDown = false) {
      const token = uni.getStorageSync('token')
      if (!token) {
        this.goToLogin()
        return
      }
      if (!showPullDown) this.loading = true

      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_active_activities', token: token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (showPullDown) uni.stopPullDownRefresh()
          if (res.data.code === 200) {
            this.activities = res.data.data.map(item => ({
              ...item,
              id: parseInt(item.id),
              has_checked: item.has_checked
            }))
          } else if (res.data.code === 401) {
            this.goToLogin()
          } else {
            uni.showToast({ title: res.data.msg || '获取活动失败', icon: 'none' })
          }
        },
        fail: () => {
          this.loading = false
          if (showPullDown) uni.stopPullDownRefresh()
          uni.showToast({ title: '网络错误', icon: 'none' })
        }
      })
    },
    goToCheckin(activityId) {
      uni.navigateTo({ url: `/pages/checkin/checkin?activity_id=${activityId}` })
    },
    goToManualApply(activityId, courseName) {
      this.currentActivityForManual = { id: activityId, name: courseName }
      this.showManualModal = true
    },
    closeManualModal() {
      this.showManualModal = false
      this.manualReason = ''
      this.currentActivityForManual = null
    },
    submitManualApply() {
      if (!this.currentActivityForManual) return
      const token = uni.getStorageSync('token')
      uni.showLoading({ title: '提交中...', mask: true })
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: {
          action: 'request_manual_checkin',
          activity_id: this.currentActivityForManual.id,
          reason: this.manualReason,
          token: token
        },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          uni.hideLoading()
          this.closeManualModal()
          if (res.data.code === 200) {
            uni.showToast({ title: '申请已提交', icon: 'success' })
            this.fetchActivities()
          } else {
            uni.showToast({ title: res.data.msg || '提交失败', icon: 'none' })
          }
        },
        fail: () => {
          uni.hideLoading()
          this.closeManualModal()
          uni.showToast({ title: '网络错误', icon: 'none' })
        }
      })
    },
    goToLogin() {
      uni.removeStorageSync('token')
      uni.removeStorageSync('user')
      uni.reLaunch({ url: '/pages/login/login' })
    }
  }
}
</script>

<style>
/* 样式与原 activity.vue 一致，但可适当简化，去除排队相关样式 */
.container {
  min-height: 100vh;
  background-color: #f5f7fa;
  padding: 30rpx;
  padding-bottom: 100rpx;
}
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 100rpx 0;
}
.loading-icon {
  width: 60rpx;
  height: 60rpx;
  border: 4rpx solid #e9ecef;
  border-top-color: #007aff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20rpx;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 200rpx 0;
}
.empty-icon {
  font-size: 120rpx;
  margin-bottom: 30rpx;
  opacity: 0.5;
}
.empty-text {
  color: #868e96;
  font-size: 32rpx;
  margin-bottom: 20rpx;
}
.empty-tip {
  color: #adb5bd;
  font-size: 28rpx;
}
.activity-item {
  background: white;
  border-radius: 24rpx;
  padding: 30rpx;
  margin-bottom: 30rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}
.course-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
}
.course-name {
  font-size: 40rpx;
  font-weight: bold;
  color: #212529;
}
.course-status {
  font-size: 24rpx;
  padding: 8rpx 16rpx;
  border-radius: 30rpx;
}
.course-status.pending {
  background: #fff3cd;
  color: #856404;
}
.course-status.active {
  background: #d4edda;
  color: #155724;
}
.course-status.ended {
  background: #e9ecef;
  color: #6c757d;
}
.course-info {
  background: #f8f9fa;
  border-radius: 16rpx;
  padding: 20rpx;
  margin: 20rpx 0;
}
.info-row {
  margin-bottom: 12rpx;
  display: flex;
}
.label {
  width: 120rpx;
  color: #868e96;
  font-size: 28rpx;
}
.value {
  flex: 1;
  color: #212529;
  font-size: 28rpx;
  word-break: break-word;
}
.wifi-row {
  background: #e7f5ff;
  margin: -10rpx -10rpx -10rpx 0;
  padding: 10rpx;
  border-radius: 12rpx;
}
.wifi-value {
  color: #007aff;
  font-weight: bold;
}
.checked-container {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  border-radius: 30rpx;
  padding: 20rpx;
}
.checked-tag {
  display: inline-block;
  background-color: #28a745;
  color: white;
  padding: 10rpx 30rpx;
  border-radius: 40rpx;
  font-size: 28rpx;
}
.button-group {
  display: flex;
  gap: 20rpx;
  margin-top: 20rpx;
}
.checkin-btn {
  flex: 1;
  background-color: #007aff;
  color: white;
  font-size: 32rpx;
  height: 80rpx;
  line-height: 80rpx;
  border-radius: 40rpx;
  border: none;
}
.manual-apply-btn {
  flex: 1;
  background-color: #17a2b8;
  color: white;
  font-size: 32rpx;
  height: 80rpx;
  line-height: 80rpx;
  border-radius: 40rpx;
  border: none;
}
.disabled-btn {
  flex: 1;
  background-color: #e9ecef;
  color: #6c757d;
  font-size: 32rpx;
  height: 80rpx;
  line-height: 80rpx;
  border-radius: 40rpx;
  border: none;
  opacity: 0.7;
}
.custom-modal {
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
.modal-textarea {
  background: #f5f5f5;
  border: 2rpx solid #e9ecef;
  padding: 30rpx;
  border-radius: 20rpx;
  font-size: 32rpx;
  margin-bottom: 30rpx;
  width: 100%;
  height: 200rpx;
  box-sizing: border-box;
}
.modal-buttons {
  display: flex;
  gap: 20rpx;
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
  border: 2rpx solid #dee2e6;
}
.modal-btn.confirm {
  background: #007aff;
  color: white;
}
</style>