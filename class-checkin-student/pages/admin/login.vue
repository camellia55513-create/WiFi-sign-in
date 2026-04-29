<template>
  <view class="login-container">
    <view class="login-box">
      <view class="title">管理员登录</view>
      <input v-model="username" placeholder="用户名" class="input" />
      <input type="password" v-model="password" placeholder="密码" class="input" />
      <button @click="login" class="btn">登录</button>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      username: '',
      password: ''
    }
  },
  methods: {
    login() {
      uni.request({
        url: 'http://8.148.31.127/class_checkin/admin_api.php',
        method: 'POST',
        data: { action: 'login', username: this.username, password: this.password },
        success: (res) => {
          if (res.data.code === 200) {
            uni.setStorageSync('admin_token', res.data.data.token)
            uni.reLaunch({ url: '/pages/admin/dashboard' })
          } else {
            uni.showToast({ title: res.data.msg, icon: 'none' })
          }
        }
      })
    }
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40rpx;
}
.login-box {
  background: white;
  border-radius: 40rpx;
  padding: 60rpx 40rpx;
  width: 100%;
}
.title {
  font-size: 48rpx;
  font-weight: bold;
  text-align: center;
  margin-bottom: 50rpx;
}
.input {
  background: #f5f5f5;
  padding: 30rpx;
  border-radius: 20rpx;
  margin-bottom: 30rpx;
}
.btn {
  background: #007aff;
  color: white;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 45rpx;
}
</style>