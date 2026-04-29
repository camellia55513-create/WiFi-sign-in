<template>
  <view class="login-container">
    <view class="login-box">
      <view class="role-switch">
        <text class="role-tab" :class="{active: role==='student'}" @click="role='student'">学生</text>
        <text class="role-tab" :class="{active: role==='teacher'}" @click="role='teacher'">教师</text>
        <text class="role-tab" :class="{active: role==='admin'}" @click="role='admin'">管理员</text>
      </view>

      <view class="title">
        {{ role === 'student' ? '课堂签到' : (role === 'teacher' ? '教师端' : '管理后台') }}
      </view>
      <view class="subtitle">
        {{ role === 'student' ? '学生登录' : (role === 'teacher' ? '教师登录' : '管理员登录') }}
      </view>

      <view class="form">
        <input
          type="text"
          v-model="account"
          :placeholder="role==='student'?'请输入学号':(role==='teacher'?'请输入工号':'请输入用户名')"
          placeholder-class="placeholder"
        />
        <input
          type="password"
          v-model="password"
          placeholder="请输入密码"
          placeholder-class="placeholder"
        />

        <view class="tip">
          <text>初始密码：123456</text>
        </view>

        <button @click="login" :disabled="loading">
          {{ loading ? '登录中...' : '登录' }}
        </button>

        <view class="links">
          <text v-if="role === 'student'" @click="goToForgotPassword('student')">忘记密码？</text>
          <text v-if="role === 'teacher'" @click="goToForgotPassword('teacher')">忘记密码？</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      role: 'student',
      account: '',
      password: '',
      loading: false
    }
  },
  methods: {
    login() {
      if (!this.account || !this.password) {
        uni.showToast({ title: '账号和密码不能为空', icon: 'none' })
        return
      }

      let url, data, tokenKey, homePage

      if (this.role === 'student') {
        url = 'http://8.148.31.127/class_checkin/api.php'
        data = { action: 'login', student_id: this.account, password: this.password }
        tokenKey = 'token'
        homePage = '/pages/index/index'
      } else if (this.role === 'teacher') {
        url = 'http://8.148.31.127/class_checkin/teacher_api.php'
        data = { action: 'login', teacher_id: this.account, password: this.password }
        tokenKey = 'teacher_token'
        homePage = '/pages/teacher/activities'
      } else {
        url = 'http://8.148.31.127/class_checkin/admin_api.php'
        data = { action: 'login', username: this.account, password: this.password }
        tokenKey = 'admin_token'
        homePage = '/pages/admin/dashboard'
      }

      this.loading = true
      uni.showLoading({ title: '登录中...', mask: true })

      uni.request({
        url: url,
        method: 'POST',
        data: data,
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          uni.hideLoading()
          this.loading = false
          if (res.data.code === 200) {
            uni.setStorageSync(tokenKey, res.data.data.token)
            uni.setStorageSync('user', res.data.data.user)
            uni.setStorageSync('role', this.role)
            uni.showToast({ title: '登录成功', icon: 'success' })
            setTimeout(() => {
              if (this.role === 'student') {
                uni.switchTab({ url: homePage })
              } else {
                uni.reLaunch({ url: homePage })
              }
            }, 1500)
          } else {
            uni.showToast({ title: res.data.msg || '登录失败', icon: 'none' })
          }
        },
        fail: () => {
          uni.hideLoading()
          this.loading = false
          uni.showToast({ title: '网络错误', icon: 'none' })
        }
      })
    },
    goToForgotPassword(role) {
      uni.navigateTo({ url: `/pages/forgot-password/forgot-password?role=${role}` })
    }
  }
}
</script>

<style scoped>
.login-container { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; padding: 40rpx; }
.login-box { background-color: rgba(255, 255, 255, 0.95); width: 100%; border-radius: 40rpx; padding: 60rpx 40rpx; box-shadow: 0 20rpx 40rpx rgba(0, 0, 0, 0.1); }
.role-switch { display: flex; justify-content: center; margin-bottom: 40rpx; border-bottom: 2rpx solid #e9ecef; padding-bottom: 20rpx; }
.role-tab { font-size: 32rpx; padding: 20rpx 40rpx; margin: 0 20rpx; color: #6c757d; border-radius: 40rpx; }
.role-tab.active { color: #007aff; background-color: #e7f5ff; font-weight: bold; }
.title { font-size: 60rpx; font-weight: bold; text-align: center; color: #333; margin-bottom: 10rpx; }
.subtitle { font-size: 32rpx; text-align: center; color: #666; margin-bottom: 60rpx; }
.form input { background-color: #f5f5f5; border: 1px solid #e9ecef; padding: 30rpx; margin-bottom: 30rpx; border-radius: 20rpx; font-size: 32rpx; }
.placeholder { color: #999; font-size: 32rpx; }
.tip { background-color: #e7f5ff; padding: 20rpx; border-radius: 16rpx; margin-bottom: 30rpx; text-align: center; }
.tip text { font-size: 28rpx; color: #007aff; }
button { background-color: #007aff; color: white; font-size: 36rpx; height: 100rpx; line-height: 100rpx; border-radius: 50rpx; margin-top: 20rpx; border: none; box-shadow: 0 10rpx 20rpx rgba(0, 122, 255, 0.3); }
button[disabled] { opacity: 0.7; }
.links { margin-top: 40rpx; padding-top: 20rpx; border-top: 1rpx solid #eee; text-align: center; }
.links text { color: #007aff; font-size: 28rpx; text-decoration: underline; }
</style>