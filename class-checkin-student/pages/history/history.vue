<template>
  <view class="container">
    <view class="header"><text class="title">签到记录</text></view>
    <view v-if="loading" class="loading-state">加载中...</view>
    <view v-else-if="errorMsg" class="error-tip">{{ errorMsg }}</view>
    <view v-else-if="history.length === 0" class="empty-state">暂无签到记录</view>
    <view v-else class="history-list">
      <view class="history-item" v-for="item in history" :key="item.id">
        <view class="history-info">
          <text class="course-name">{{ item.course_name }}</text>
          <text class="checkin-time">{{ formatTime(item.checkin_time) }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() { return { history: [], loading: false, errorMsg: '', page: 1 } },
  onShow() { this.fetchHistory() },
  methods: {
    fetchHistory() {
      const token = uni.getStorageSync('token')
      if (!token) return uni.reLaunch({ url: '/pages/login/login' })
      this.loading = true
      this.errorMsg = ''
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_checkin_history', token, page: this.page },
        success: (res) => {
          this.loading = false
          if (res.data.code === 200) this.history = res.data.data
          else this.errorMsg = res.data.msg || '获取失败'
        },
        fail: () => { this.loading = false; this.errorMsg = '网络错误' }
      })
    },
    formatTime(t) { if(!t) return ''; const d=new Date(t); return `${d.getFullYear()}-${d.getMonth()+1}-${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}` }
  }
}
</script>


<style scoped>
.container { min-height:100vh; background:#f5f7fa; padding:30rpx; }
.header { margin-bottom:30rpx; }
.title { font-size:40rpx; font-weight:bold; }
.loading-state, .empty-state { text-align:center; padding:200rpx 0; color:#868e96; }
.error-tip { text-align:center; padding:100rpx; color:#dc3545; }
.history-list { background:#fff; border-radius:24rpx; overflow:hidden; }
.history-item { display:flex; justify-content:space-between; align-items:center; padding:30rpx; border-bottom:1rpx solid #f1f3f5; }
.course-name { font-size:32rpx; font-weight:bold; display:block; }
.checkin-time { font-size:26rpx; color:#6c757d; }
</style>