<template>
  <view class="container">
    <view class="header">
      <text class="title">{{ courseName }}</text>
    </view>

    <view class="activity-tabs">
      <view class="tab" :class="{active: tabIndex===0}" @click="tabIndex=0">进行中</view>
      <view class="tab" :class="{active: tabIndex===1}" @click="tabIndex=1">历史</view>
    </view>

    <view v-if="loading" class="loading-state">加载中...</view>
    <view v-else>
      <view v-if="filteredList.length === 0" class="empty-state">暂无活动</view>
      <view v-else class="activity-list">
        <view class="activity-item" v-for="item in filteredList" :key="item.id">
          <view class="course-name">{{ item.course_name }}</view>
          <view class="info">教师：{{ item.teacher_name }}</view>
          <view class="info">时间：{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</view>
          <view class="info">地点：{{ item.location || '未设置' }}</view>
          <view class="info">WiFi：{{ item.wifi_ssid }}</view>
          <view class="status-badge" :class="item.status">{{ getStatusText(item.status) }}</view>
          <view v-if="item.has_checked" class="checked-tag">✅ 已签到</view>
          <button v-else-if="item.status === 'active'" class="checkin-btn" @click="goCheckin(item.id)">立即签到</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      courseId: '',
      courseName: '',
      activities: [],
      loading: false,
      tabIndex: 0
    }
  },
  computed: {
    filteredList() {
      if (this.tabIndex === 0) {
        return this.activities.filter(a => a.status === 'active' || a.status === 'pending')
      } else {
        return this.activities.filter(a => a.status === 'ended' || a.has_checked)
      }
    }
  },
  onLoad(options) {
    this.courseId = options.course_id
    this.courseName = decodeURIComponent(options.course_name || '')
    this.fetchActivities()
  },
  methods: {
    fetchActivities() {
      const token = uni.getStorageSync('token')
      if (!token) return uni.reLaunch({ url: '/pages/login/login' })
      this.loading = true
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_activities', course_id: this.courseId, token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) this.activities = res.data.data
        },
        fail: () => { this.loading = false }
      })
    },
    formatTime(t) { return t?.replace('T', ' ').substring(0,16) },
    getStatusText(s) { return {pending:'未开始', active:'进行中', ended:'已结束'}[s] },
    goCheckin(id) { uni.navigateTo({ url: `/pages/checkin/checkin?activity_id=${id}` }) }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background: #f5f7fa; padding: 30rpx; }
.header { margin-bottom: 30rpx; }
.title { font-size: 40rpx; font-weight: bold; }
.activity-tabs { display: flex; background: white; border-radius: 40rpx; margin-bottom: 20rpx; padding: 10rpx; }
.tab { flex: 1; text-align: center; padding: 20rpx; border-radius: 30rpx; color: #6c757d; }
.tab.active { background: #007aff; color: white; }
.loading-state, .empty-state { text-align: center; padding: 100rpx 0; color: #868e96; }
.activity-item { background: white; border-radius: 20rpx; padding: 30rpx; margin-bottom: 20rpx; position: relative; }
.course-name { font-size: 36rpx; font-weight: bold; margin-bottom: 10rpx; }
.info { color: #495057; font-size: 28rpx; margin-bottom: 5rpx; }
.status-badge { position: absolute; top: 30rpx; right: 30rpx; padding: 6rpx 16rpx; border-radius: 30rpx; font-size: 24rpx; }
.status-badge.active { background: #d4edda; color: #155724; }
.status-badge.ended { background: #e9ecef; color: #6c757d; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.checked-tag { color: #28a745; margin-top: 20rpx; }
.checkin-btn { background: #007aff; color: white; margin-top: 20rpx; border-radius: 40rpx; }
</style>