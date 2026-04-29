<template>
  <view class="container">
    <view class="user-card">
      <view class="avatar">👨‍🏫</view>
      <view class="user-info">
        <text class="name">{{ teacherInfo.name || '加载中...' }}</text>
        <text class="teacher-id">工号：{{ teacherInfo.teacher_id || '加载中...' }}</text>
      </view>
    </view>

    <view class="menu-list">
      <view class="menu-item" @click="showChangePwd = true">
        <text class="menu-icon">🔐</text>
        <text class="menu-text">修改密码</text>
      </view>
      <view class="menu-item" @click="logout">
        <text class="menu-icon">🚪</text>
        <text class="menu-text">退出登录</text>
      </view>
    </view>

    <!-- 修改密码弹窗（无需原密码） -->
    <view class="modal" v-if="showChangePwd" @click="showChangePwd = false">
      <view class="modal-content" @click.stop>
        <view class="modal-title">修改密码</view>
        <input type="password" v-model="newPassword" placeholder="新密码（至少6位）" class="modal-input" />
        <input type="password" v-model="confirmPassword" placeholder="确认新密码" class="modal-input" />
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="showChangePwd = false">取消</button>
          <button class="modal-btn confirm" @click="changePassword">确认</button>
        </view>
      </view>
    </view>

    <view class="bottom-nav">
      <view class="nav-item" @click="switchTab('activities')">📋 活动</view>
      <view class="nav-item" @click="switchTab('course')">📚 课程</view>
      <view class="nav-item active">👤 我的</view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      teacherInfo: {},
      showChangePwd: false,
      newPassword: '',
      confirmPassword: ''
    }
  },
  onShow() {
    const user = uni.getStorageSync('teacher_user');
    if (user && user.name) {
      this.teacherInfo = user;
    } else {
      const token = uni.getStorageSync('teacher_token');
      if (token) {
        uni.request({
          url: 'http://8.148.31.127/class_checkin/teacher_api.php',
          method: 'POST',
          data: { action: 'get_teacher_info', token },
          success: (res) => {
            if (res.data.code === 200) {
              this.teacherInfo = res.data.data;
              uni.setStorageSync('teacher_user', res.data.data);
            }
          }
        });
      }
    }
  },
  methods: {
    changePassword() {
      if (this.newPassword.length < 6) { uni.showToast({ title: '密码至少6位', icon: 'none' }); return }
      if (this.newPassword !== this.confirmPassword) { uni.showToast({ title: '两次密码不一致', icon: 'none' }); return }
      const token = uni.getStorageSync('teacher_token');
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { action: 'change_password', new_password: this.newPassword, token },
        success: (res) => {
          if (res.data.code === 200) {
            uni.showToast({ title: '修改成功', icon: 'success' });
            this.showChangePwd = false;
            this.newPassword = ''; this.confirmPassword = '';
          } else {
            uni.showToast({ title: res.data.msg, icon: 'none' });
          }
        }
      });
    },
    logout() {
      uni.removeStorageSync('teacher_token'); uni.removeStorageSync('teacher_user');
      uni.reLaunch({ url: '/pages/login/login' });
    },
    switchTab(tab) {
      if (tab === 'activities') uni.reLaunch({ url: '/pages/teacher/activities' });
      else if (tab === 'course') uni.reLaunch({ url: '/pages/teacher/course' });
    }
  }
}
</script>



<style scoped>
.container { min-height: 100vh; background: #f5f7fa; padding: 30rpx; padding-bottom: 120rpx; }
.user-card { background: white; border-radius: 24rpx; padding: 40rpx; margin-bottom: 30rpx; display: flex; align-items: center; }
.avatar { width: 120rpx; height: 120rpx; border-radius: 60rpx; background: #007aff; color: white; font-size: 60rpx; display: flex; align-items: center; justify-content: center; margin-right: 30rpx; }
.user-info { flex: 1; }
.name { font-size: 40rpx; font-weight: bold; display: block; }
.teacher-id { font-size: 28rpx; color: #6c757d; }
.menu-list { background: white; border-radius: 24rpx; }
.menu-item { display: flex; align-items: center; padding: 30rpx; border-bottom: 1rpx solid #f1f3f5; }
.menu-icon { font-size: 40rpx; margin-right: 20rpx; }
.menu-text { flex: 1; font-size: 32rpx; }
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; border-radius: 30rpx; padding: 40rpx; width: 600rpx; }
.modal-title { font-size: 36rpx; font-weight: bold; text-align: center; margin-bottom: 30rpx; }
.modal-input { background: #f5f5f5; padding: 25rpx; border-radius: 16rpx; margin-bottom: 20rpx; }
.modal-buttons { display: flex; gap: 20rpx; }
.modal-btn { flex: 1; height: 80rpx; line-height: 80rpx; border-radius: 40rpx; }
.modal-btn.cancel { background: #f8f9fa; color: #6c757d; }
.modal-btn.confirm { background: #007aff; color: white; }
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; display: flex; border-top: 1rpx solid #eee; padding: 10rpx 0; }
.nav-item { flex: 1; text-align: center; }
.nav-item.active .nav-text { color: #007aff; }
.nav-icon { font-size: 40rpx; display: block; }
.nav-text { font-size: 24rpx; color: #6c757d; }
</style>