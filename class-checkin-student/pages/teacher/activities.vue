<template>
  <view class="container">
    <view class="header">
      <text class="title">我的活动</text>
      <button class="create-btn" @click="goToCreate">+ 创建</button>
    </view>

    <!-- 课程筛选 -->
    <view class="course-filter" v-if="courses.length > 0">
      <picker mode="selector" :range="courseOptions" range-key="name" @change="onCourseChange">
        <view class="picker-display">
          <text>{{ selectedCourseName }}</text>
          <text class="arrow">▼</text>
        </view>
      </picker>
    </view>

    <view v-if="loading" class="loading-state">
      <view class="loading-icon"></view>
      <text>加载中...</text>
    </view>
    <view v-else-if="activities.length === 0" class="empty-state">
      <text>暂无活动，点击“+ 创建”添加</text>
    </view>
    <view v-else class="activity-list">
      <view class="activity-item" v-for="item in activities" :key="item.id" @click="viewDetail(item.id)">
        <view class="activity-header">
          <text class="course-name">{{ item.course_name }}</text>
          <text class="activity-status" :class="item.statusClass">{{ item.statusText }}</text>
        </view>
        <view class="activity-info">
          <text>📶 {{ item.wifi_ssid }}</text>
          <text>⏰ {{ item.start_time_format }} - {{ item.end_time_format }}</text>
          <text>📍 {{ item.location || '未设置' }}</text>
          <text>👥 已签到：{{ item.checkin_count || 0 }} 人</text>
        </view>
        <view class="activity-footer">
          <text class="view-detail">查看详情 ›</text>
          <button v-if="item.isActive" class="end-btn" @click.stop="endActivity(item.id)">提前结束</button>
        </view>
      </view>
    </view>

    <view class="bottom-nav">
      <view class="nav-item active">
        <text class="nav-icon">📋</text><text class="nav-text">活动</text>
      </view>
      <view class="nav-item" @click="switchTab('course')">
        <text class="nav-icon">📚</text><text class="nav-text">课程</text>
      </view>
      <view class="nav-item" @click="switchTab('profile')">
        <text class="nav-icon">👤</text><text class="nav-text">我的</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      courses: [],
      selectedCourseId: 0,
      activities: [],
      loading: false
    }
  },
  computed: {
    courseOptions() {
      return [{ id: 0, name: '全部课程' }, ...this.courses.map(c => ({ id: c.id, name: c.course_name }))]
    },
    selectedCourseName() {
      if (this.selectedCourseId === 0) return '全部课程'
      const course = this.courses.find(c => c.id === this.selectedCourseId)
      return course ? course.course_name : '全部课程'
    }
  },
  onShow() {
    this.checkLogin()
    this.fetchCourses()
  },
  methods: {
    checkLogin() {
      const token = uni.getStorageSync('teacher_token')
      if (!token) uni.reLaunch({ url: '/pages/login/login' })
    },
    fetchCourses() {
      const token = uni.getStorageSync('teacher_token')
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { action: 'get_my_courses', token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          if (res.data.code === 200) {
            this.courses = res.data.data
            this.fetchActivities()
          }
        }
      })
    },
    fetchActivities() {
      this.loading = true
      const token = uni.getStorageSync('teacher_token')
      const data = { action: 'get_my_activities', token }
      if (this.selectedCourseId > 0) data.course_id = this.selectedCourseId
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data,
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) {
            this.activities = res.data.data.map(item => this.processActivityItem(item))
          }
        },
        fail: () => { this.loading = false }
      })
    },
    processActivityItem(item) {
      const now = new Date()
      const start = new Date(item.start_time)
      const end = new Date(item.end_time)
      let statusText = '', statusClass = '', isActive = false
      if (now < start) { statusText = '未开始'; statusClass = 'status-pending'; }
      else if (now > end) { statusText = '已结束'; statusClass = 'status-ended'; }
      else { statusText = '进行中'; statusClass = 'status-active'; isActive = true; }
      const format = (t) => t ? t.replace('T', ' ').substring(0, 16) : ''
      return { ...item, statusText, statusClass, isActive, start_time_format: format(item.start_time), end_time_format: format(item.end_time) }
    },
    onCourseChange(e) {
      const index = e.detail.value
      this.selectedCourseId = this.courseOptions[index].id
      this.fetchActivities()
    },
    goToCreate() {
      // 修改为跳转到课程列表
      uni.navigateTo({ url: '/pages/teacher/course' })
    },
    viewDetail(id) {
      uni.navigateTo({ url: `/pages/teacher/activity-detail?activity_id=${id}` })
    },
    endActivity(id) {
      uni.showModal({
        title: '提示', content: '确定提前结束吗？',
        success: (res) => {
          if (res.confirm) {
            const token = uni.getStorageSync('teacher_token')
            uni.request({
              url: 'http://8.148.31.127/class_checkin/teacher_api.php',
              method: 'POST',
              data: { action: 'end_activity', activity_id: id, token },
              success: (res) => {
                if (res.data.code === 200) {
                  uni.showToast({ title: '已结束', icon: 'success' })
                  this.fetchActivities()
                }
              }
            })
          }
        }
      })
    },
    switchTab(tab) {
      if (tab === 'course') uni.redirectTo({ url: '/pages/teacher/course' })
      else if (tab === 'profile') uni.redirectTo({ url: '/pages/teacher/profile' })
    }
  }
}
</script>


<style scoped>
.container { min-height: 100vh; background-color: #f5f7fa; padding: 30rpx; padding-bottom: 120rpx; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; }
.title { font-size: 40rpx; font-weight: bold; }
.create-btn { background: #007aff; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 28rpx; }
.course-filter { background: white; border-radius: 20rpx; padding: 20rpx 30rpx; margin-bottom: 20rpx; }
.picker-display { display: flex; justify-content: space-between; font-size: 30rpx; }
.arrow { color: #adb5bd; }
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 100rpx 0; }
.loading-icon { width: 60rpx; height: 60rpx; border: 4rpx solid #e9ecef; border-top-color: #007aff; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20rpx; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.empty-state { text-align: center; padding: 200rpx 0; color: #868e96; }
.activity-item { background: white; border-radius: 24rpx; padding: 30rpx; margin-bottom: 20rpx; }
.activity-header { display: flex; justify-content: space-between; margin-bottom: 20rpx; }
.course-name { font-size: 36rpx; font-weight: bold; }
.activity-status { padding: 6rpx 16rpx; border-radius: 30rpx; font-size: 24rpx; }
.status-active { background: #d4edda; color: #155724; }
.status-pending { background: #fff3cd; color: #856404; }
.status-ended { background: #e9ecef; color: #6c757d; }
.activity-info text { display: block; font-size: 28rpx; color: #495057; margin-bottom: 10rpx; }
.activity-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 20rpx; }
.view-detail { color: #007aff; }
.end-btn { background: #dc3545; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 26rpx; }
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; display: flex; border-top: 1rpx solid #eee; padding: 10rpx 0; }
.nav-item { flex: 1; text-align: center; padding: 10rpx 0; }
.nav-item.active .nav-text { color: #007aff; }
.nav-icon { font-size: 40rpx; display: block; }
.nav-text { font-size: 24rpx; color: #6c757d; }
</style>