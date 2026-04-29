<template>
  <view class="success-container">
    <view class="success-card">
      <view class="success-icon">✅</view>
      <text class="success-title">签到成功！</text>
      <view class="button-group">
        <button @click="goHome" class="home-btn">返回首页</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  methods: {
    goHome() {
      uni.switchTab({ url: '/pages/index/index' })
    }
  }
}
</script>

<style scoped>
.success-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40rpx;
}
.success-card {
  background: white;
  border-radius: 40rpx;
  padding: 80rpx 40rpx;
  width: 100%;
  text-align: center;
}
.success-icon {
  font-size: 160rpx;
  margin-bottom: 40rpx;
}
.success-title {
  font-size: 60rpx;
  font-weight: bold;
  color: #28a745;
}
.button-group {
  margin-top: 60rpx;
}
.home-btn {
  background: #007aff;
  color: white;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 45rpx;
}
</style>