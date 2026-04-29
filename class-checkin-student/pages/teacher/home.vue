<template>
  <view class="app-container">
    <!-- 侧边栏 -->
    <view class="sidebar" :class="{ open: sidebarOpen }">
      <view class="sidebar-header">
        <text class="sidebar-title">我的课程</text>
        <button class="close-btn" @click="sidebarOpen = false">✕</button>
      </view>
      <scroll-view class="course-scroll" scroll-y>
        <view
          class="course-item"
          v-for="course in courses"
          :key="course.id"
          :class="{ active: selectedCourse && selectedCourse.id === course.id }"
          @click="selectCourse(course)"
        >
          <text class="course-name">{{ course.course_name }}</text>
          <text class="course-location">{{ course.location || '未设置教室' }}</text>
        </view>
      </scroll-view>
      <view class="sidebar-footer">
        <button class="create-btn" @click="goCreate">+ 创建活动</button>
        <view class="menu-item" @click="goToProfile">
          <text class="menu-icon">👤</text>
          <text class="menu-text">我的</text>
        </view>
      </view>
    </view>

    <view class="overlay" v-if="sidebarOpen" @click="sidebarOpen = false"></view>

    <!-- 主内容区 -->
    <view class="main-content">
      <view class="top-bar">
        <button class="menu-btn" @click="sidebarOpen = !sidebarOpen">☰</button>
        <text class="page-title">{{ pageTitle }}</text>
      </view>

      <view v-if="!selectedCourse" class="welcome-box">
        <text class="welcome-text">请从左侧课程列表选择课程</text>
        <text class="welcome-sub">管理该课程的签到活动</text>
      </view>

      <view v-else-if="loading" class="loading-state">加载中...</view>

      <view v-else-if="activities.length === 0" class="empty-state">
        <text>该课程暂无签到活动</text>
      </view>

      <view v-else class="activity-list">
        <view class="activity-item" v-for="item in activities" :key="item.id">
          <view class="act-header">
            <text class="act-name">{{ item.course_name }}</text>
            <!-- 直接使用预处理属性 -->
            <text class="act-status" :class="item.statusClass">{{ item.statusText }}</text>
          </view>
          <view class="act-info">
            <text>📶 {{ item.wifi_ssid }}</text>
            <text>⏰ {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</text>
            <text>👥 已签到：{{ item.checkin_count || 0 }} 人</text>
          </view>
          <view class="act-footer">
            <button class="detail-btn" @click="viewDetail(item.id)">查看详情</button>
            <!-- 使用 isActive 属性 -->
            <button v-if="item.isActive" class="end-btn" @click="endActivity(item.id)">提前结束</button>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      sidebarOpen: false,
      courses: [],
      selectedCourse: null,
      activities: [],
      loading: false
    }
  },
  computed: {
    pageTitle() {
      return this.selectedCourse ? this.selectedCourse.course_name : '教师工作台'
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
          if (res.data.code === 200) this.courses = res.data.data
        }
      })
    },
    selectCourse(course) {
      this.selectedCourse = course
      this.sidebarOpen = false
      this.fetchActivities(course.id)
    },
    fetchActivities(courseId) {
      this.loading = true
      const token = uni.getStorageSync('teacher_token')
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { action: 'get_course_activities', course_id: courseId, token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) {
            // 预处理活动状态
            this.activities = res.data.data.map(item => {
              const now = new Date()
              const start = new Date(item.start_time)
              const end = new Date(item.end_time)
              const isActive = now >= start && now <= end
              return {
                ...item,
                statusText: now < start ? '未开始' : (now > end ? '已结束' : '进行中'),
                statusClass: now < start ? 'pending' : (now > end ? 'ended' : 'active'),
                isActive: isActive
              }
            })
          }
        },
        fail: () => { this.loading = false }
      })
    },
    formatTime(timeStr) {
      if (!timeStr) return ''
      return timeStr.replace('T', ' ').substring(0, 16)
    },
    viewDetail(id) {
      uni.navigateTo({ url: `/pages/teacher/activity-detail?activity_id=${id}` })
    },
    endActivity(id) {
      uni.showModal({
        title: '提示',
        content: '确定提前结束该活动吗？',
        success: (res) => {
          if (res.confirm) {
            const token = uni.getStorageSync('teacher_token')
            uni.request({
              url: 'http://8.148.31.127/class_checkin/teacher_api.php',
              method: 'POST',
              data: { action: 'end_activity', activity_id: id, token },
              header: { 'Content-Type': 'application/x-www-form-urlencoded' },
              success: (res) => {
                if (res.data.code === 200) {
                  uni.showToast({ title: '已结束', icon: 'success' })
                  this.fetchActivities(this.selectedCourse.id)
                }
              }
            })
          }
        }
      })
    },
    goCreate() {
      uni.navigateTo({ url: '/pages/teacher/create-activity' })
    },
    goToProfile() {
      uni.navigateTo({ url: '/pages/teacher/profile' })
    }
  }
}
</script>

<style scoped>
.app-container { display: flex; height: 100vh; background: #f5f7fa; position: relative; }
.sidebar {
  position: fixed; top: 0; left: -500rpx; bottom: 0; width: 500rpx;
  background: white; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  z-index: 200; transition: left 0.3s; display: flex; flex-direction: column;
}
.sidebar.open { left: 0; }
.sidebar-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 30rpx; border-bottom: 1rpx solid #eee;
}
.sidebar-title { font-size: 36rpx; font-weight: bold; }
.close-btn { background: none; font-size: 40rpx; color: #666; padding: 10rpx; }
.course-scroll { flex: 1; padding: 20rpx; }
.course-item {
  padding: 30rpx; margin-bottom: 15rpx; border-radius: 16rpx;
  background: #f8f9fa; border-left: 6rpx solid transparent;
}
.course-item.active { background: #e7f3ff; border-left-color: #007aff; }
.course-name { font-size: 32rpx; font-weight: bold; display: block; margin-bottom: 8rpx; }
.course-location { font-size: 26rpx; color: #6c757d; }
.sidebar-footer {
  padding: 30rpx; border-top: 1rpx solid #eee; display: flex; align-items: center; justify-content: space-between;
}
.create-btn { background: #007aff; color: white; padding: 20rpx 30rpx; border-radius: 30rpx; }
.menu-item { display: flex; flex-direction: column; align-items: center; }
.menu-icon { font-size: 44rpx; }
.menu-text { font-size: 24rpx; color: #495057; margin-top: 8rpx; }

.overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3); z-index: 199; }

.main-content { flex: 1; padding: 30rpx; }
.top-bar { display: flex; align-items: center; margin-bottom: 30rpx; }
.menu-btn { background: #007aff; color: white; padding: 10rpx 20rpx; border-radius: 10rpx; margin-right: 20rpx; }
.page-title { font-size: 40rpx; font-weight: bold; }
.welcome-box { text-align: center; padding: 200rpx 0; }
.welcome-text { font-size: 32rpx; color: #868e96; }
.welcome-sub { font-size: 26rpx; color: #adb5bd; margin-top: 20rpx; }
.loading-state, .empty-state { text-align: center; padding: 100rpx 0; color: #868e96; }

.activity-item { background: white; border-radius: 24rpx; padding: 30rpx; margin-bottom: 20rpx; }
.act-header { display: flex; justify-content: space-between; margin-bottom: 20rpx; }
.act-name { font-size: 36rpx; font-weight: bold; }
.act-status { padding: 6rpx 16rpx; border-radius: 30rpx; font-size: 24rpx; }
.act-status.active { background: #d4edda; color: #155724; }
.act-status.ended { background: #e9ecef; color: #6c757d; }
.act-status.pending { background: #fff3cd; color: #856404; }
.act-info text { display: block; font-size: 28rpx; color: #495057; margin-bottom: 8rpx; }
.act-footer { display: flex; justify-content: space-between; margin-top: 20rpx; }
.detail-btn { background: #e9ecef; color: #495057; padding: 15rpx 30rpx; border-radius: 30rpx; }
.end-btn { background: #dc3545; color: white; padding: 15rpx 30rpx; border-radius: 30rpx; }
</style>