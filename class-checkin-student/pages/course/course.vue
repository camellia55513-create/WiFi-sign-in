<template>
  <view class="container">
    <view class="header">
      <text class="title">我的课程</text>
    </view>

    <view v-if="loading" class="loading-state">
      <view class="loading-icon"></view>
      <text>加载中...</text>
    </view>

    <view v-else-if="courses.length === 0" class="empty-state">
      <text>暂无课程，请联系管理员选课</text>
    </view>

    <view v-else class="course-list">
      <view class="course-item" v-for="item in courses" :key="item.id" @click="goToCourseActivities(item)">
        <view class="course-icon">📚</view>
        <view class="course-info">
          <text class="course-name">{{ item.course_name }}</text>
          <text class="teacher-name">教师：{{ item.teacher_name }}</text>
        </view>
        <view class="arrow">›</view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      courses: [],
      loading: false
    }
  },
  onShow() {
    this.fetchCourses()
  },
  methods: {
    fetchCourses() {
      const token = uni.getStorageSync('token')
      if (!token) {
        uni.reLaunch({ url: '/pages/login/login' })
        return
      }
      this.loading = true
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_courses', token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) {
            this.courses = res.data.data
          } else {
            uni.showToast({ title: res.data.msg || '获取失败', icon: 'none' })
          }
        },
        fail: () => {
          this.loading = false
          uni.showToast({ title: '网络错误', icon: 'none' })
        }
      })
    },
    goToCourseActivities(course) {
      uni.navigateTo({ 
        url: `/pages/course-activities/course-activities?course_id=${course.id}&course_name=${encodeURIComponent(course.course_name)}` 
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
.header {
  margin-bottom: 30rpx;
}
.title {
  font-size: 48rpx;
  font-weight: bold;
  color: #333;
}
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
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
  text-align: center;
  color: #868e96;
  padding: 200rpx 0;
}
.course-list {
  background: white;
  border-radius: 24rpx;
  overflow: hidden;
}
.course-item {
  display: flex;
  align-items: center;
  padding: 30rpx;
  border-bottom: 1rpx solid #f1f3f5;
}
.course-item:last-child {
  border-bottom: none;
}
.course-icon {
  font-size: 60rpx;
  margin-right: 30rpx;
}
.course-info {
  flex: 1;
}
.course-name {
  font-size: 34rpx;
  font-weight: bold;
  color: #212529;
  display: block;
  margin-bottom: 8rpx;
}
.teacher-name {
  font-size: 26rpx;
  color: #6c757d;
}
.arrow {
  font-size: 40rpx;
  color: #adb5bd;
}
</style>