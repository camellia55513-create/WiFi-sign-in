<template>
  <view class="container">
    <view class="header">
      <text class="title">我的课程</text>
    </view>

    <view v-if="loading" class="loading-state">加载中...</view>
    <view v-else-if="courses.length === 0" class="empty-state">
      <text>暂无课程，请联系管理员分配</text>
    </view>

    <view v-else class="course-list">
      <view class="course-item" v-for="item in courses" :key="item.id" @click="goToCourseDetail(item)">
        <view class="course-icon">📚</view>
        <view class="course-info">
          <text class="course-name">{{ item.course_name }}</text>
          <text class="course-location">{{ item.location || '未设置教室' }}</text>
        </view>
        <view class="arrow">›</view>
      </view>
    </view>

    <!-- 底部导航 -->
    <view class="bottom-nav">
      <view class="nav-item" @click="switchTab('activities')">
        <text class="nav-icon">📋</text><text class="nav-text">活动</text>
      </view>
      <view class="nav-item active">
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
    return { courses: [], loading: false }
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
      if (!token) return
      this.loading = true
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { action: 'get_my_courses', token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) {
            this.courses = res.data.data
          } else {
            uni.showToast({ title: res.data.msg || '获取课程失败', icon: 'none' })
          }
        },
        fail: () => { this.loading = false; uni.showToast({ title: '网络错误', icon: 'none' }) }
      })
    },
    goToCourseDetail(course) {
      uni.navigateTo({
        url: `/pages/teacher/course-detail?course_id=${course.id}&course_name=${encodeURIComponent(course.course_name)}`
      })
    },
    switchTab(tab) {
      if (tab === 'activities') uni.redirectTo({ url: '/pages/teacher/activities' })
      else if (tab === 'profile') uni.redirectTo({ url: '/pages/teacher/profile' })
    }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background-color: #f5f7fa; padding: 30rpx; padding-bottom: 120rpx; }
.header { margin-bottom: 30rpx; }
.title { font-size: 40rpx; font-weight: bold; }
.loading-state, .empty-state { text-align: center; padding: 100rpx 0; color: #868e96; }
.course-list { background: white; border-radius: 24rpx; overflow: hidden; }
.course-item { display: flex; align-items: center; padding: 30rpx; border-bottom: 1rpx solid #f1f3f5; }
.course-item:last-child { border-bottom: none; }
.course-icon { font-size: 60rpx; margin-right: 30rpx; }
.course-info { flex: 1; }
.course-name { font-size: 34rpx; font-weight: bold; display: block; margin-bottom: 8rpx; }
.course-location { font-size: 26rpx; color: #6c757d; }
.arrow { font-size: 40rpx; color: #adb5bd; }
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; display: flex; border-top: 1rpx solid #eee; padding: 10rpx 0; }
.nav-item { flex: 1; text-align: center; padding: 10rpx 0; }
.nav-item.active .nav-text { color: #007aff; }
.nav-icon { font-size: 40rpx; display: block; }
.nav-text { font-size: 24rpx; color: #6c757d; }
</style>