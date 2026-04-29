<template>
  <view class="container">
    <view class="card">
      <view class="title">找回密码</view>
      <view class="subtitle">{{ roleText }}找回密码</view>

      <view v-if="step === 1">
        <input v-model="account" :placeholder="role === 'student' ? '学号' : '工号'" class="input" />
        <input v-model="name" placeholder="姓名" class="input" />
        <button @click="verifyIdentity" class="btn">下一步</button>
      </view>

      <view v-else-if="step === 2">
        <input type="password" v-model="newPassword" placeholder="新密码（至少6位）" class="input" />
        <input type="password" v-model="confirmPassword" placeholder="确认新密码" class="input" />
        <button @click="resetPassword" class="btn">确认重置</button>
      </view>

      <view v-else>
        <view class="success-icon">✅</view>
        <text class="success-text">密码重置成功</text>
        <button @click="goToLogin" class="btn">去登录</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      role: 'student',
      step: 1,
      account: '',
      name: '',
      newPassword: '',
      confirmPassword: ''
    }
  },
  computed: {
    roleText() {
      return this.role === 'student' ? '学生' : '教师'
    }
  },
  onLoad(options) {
    if (options.role) {
      this.role = options.role
    }
  },
  methods: {
    verifyIdentity() {
      if (!this.account || !this.name) {
        uni.showToast({ title: '请填写完整', icon: 'none' })
        return
      }
      const url = this.role === 'student' 
        ? 'http://8.148.31.127/class_checkin/api.php' 
        : 'http://8.148.31.127/class_checkin/teacher_api.php'
      const data = this.role === 'student'
        ? { action: 'verify_student', student_id: this.account, name: this.name }
        : { action: 'verify_teacher', teacher_id: this.account, name: this.name }
      
      uni.request({
        url, method: 'POST', data,
        success: (res) => {
          if (res.data.code === 200) {
            this.step = 2
          } else {
            uni.showToast({ title: res.data.msg, icon: 'none' })
          }
        }
      })
    },
    resetPassword() {
      if (this.newPassword.length < 6) {
        uni.showToast({ title: '密码至少6位', icon: 'none' })
        return
      }
      if (this.newPassword !== this.confirmPassword) {
        uni.showToast({ title: '两次密码不一致', icon: 'none' })
        return
      }
      const url = this.role === 'student'
        ? 'http://8.148.31.127/class_checkin/api.php'
        : 'http://8.148.31.127/class_checkin/teacher_api.php'
      const data = this.role === 'student'
        ? { action: 'reset_password', student_id: this.account, new_password: this.newPassword }
        : { action: 'reset_teacher_password', teacher_id: this.account, new_password: this.newPassword }
      
      uni.request({
        url, method: 'POST', data,
        success: (res) => {
          if (res.data.code === 200) {
            this.step = 3
          } else {
            uni.showToast({ title: res.data.msg, icon: 'none' })
          }
        }
      })
    },
    goToLogin() {
      uni.reLaunch({ url: '/pages/login/login' })
    }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; padding: 40rpx; }
.card { background: white; border-radius: 40rpx; padding: 60rpx 40rpx; width: 100%; }
.title { font-size: 48rpx; font-weight: bold; text-align: center; margin-bottom: 20rpx; }
.subtitle { font-size: 28rpx; text-align: center; color: #666; margin-bottom: 50rpx; }
.input { background: #f5f5f5; padding: 30rpx; border-radius: 20rpx; margin-bottom: 30rpx; font-size: 32rpx; }
.btn { background: #007aff; color: white; height: 90rpx; line-height: 90rpx; border-radius: 45rpx; margin-top: 20rpx; }
.success-icon { font-size: 120rpx; text-align: center; margin-bottom: 30rpx; }
.success-text { display: block; text-align: center; font-size: 36rpx; color: #28a745; margin-bottom: 60rpx; }
</style>