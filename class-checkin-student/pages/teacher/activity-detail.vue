<template>
  <view class="detail-container">
    <view v-if="loading" class="loading-state">
      <view class="loading-icon"></view>
      <text>加载中...</text>
    </view>

    <view v-else-if="errorMsg" class="error-tip">
      <text>{{ errorMsg }}</text>
    </view>

    <view v-else>
      <!-- 活动信息卡片 -->
      <view class="info-card" v-if="activity">
        <text class="course-name">{{ activity.course_name }}</text>
        <text class="teacher">教师：{{ activity.teacher_name }}</text>
        <text class="wifi">📶 {{ activity.wifi_ssid }} ({{ activity.bssid }})</text>
        <text class="time">⏰ {{ formatTime(activity.start_time) }} - {{ formatTime(activity.end_time) }}</text>
        <text class="location">📍 {{ activity.location || '未设置' }}</text>
      </view>

      <!-- 统计卡片 -->
      <view class="stats-card">
        <view class="stat-item">
          <text class="stat-number">{{ checkedList.length }}</text>
          <text class="stat-label">已签到</text>
        </view>
        <view class="stat-item">
          <text class="stat-number">{{ uncheckedList.length }}</text>
          <text class="stat-label">未签到</text>
        </view>
        <view class="stat-item">
          <text class="stat-number">{{ absentList.length }}</text>
          <text class="stat-label">缺勤</text>
        </view>
      </view>

      <!-- 标签页 -->
      <view class="tabs">
        <view class="tab" :class="{ active: currentTab === 'checked' }" @click="currentTab = 'checked'">
          已签到 ({{ checkedList.length }})
        </view>
        <view class="tab" :class="{ active: currentTab === 'unchecked' }" @click="currentTab = 'unchecked'">
          未签到 ({{ uncheckedList.length }})
        </view>
        <view class="tab" :class="{ active: currentTab === 'absent' }" @click="currentTab = 'absent'">
          缺勤 ({{ absentList.length }})
        </view>
      </view>

      <!-- 已签到列表 -->
      <view v-if="currentTab === 'checked'" class="list">
        <view v-if="checkedList.length === 0" class="empty-list">暂无签到记录</view>
        <view v-else class="list-item" v-for="item in checkedList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.name }}</text>
            <text class="student-id">{{ item.stu_num }}</text>
          </view>
          <view class="item-time">
            <text class="time">{{ formatTime(item.checkin_time) }}</text>
            <!-- 不再区分迟到，统一显示“正常” -->
            <text class="checkin-type">正常</text>
          </view>
        </view>
      </view>

      <!-- 未签到列表 -->
      <view v-if="currentTab === 'unchecked'" class="list">
        <view v-if="uncheckedList.length === 0" class="empty-list">所有学生均已签到</view>
        <view v-else class="list-item" v-for="item in uncheckedList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.name }}</text>
            <text class="student-id">{{ item.student_id }}</text>
          </view>
          <view class="item-actions">
            <button class="manual-checkin-btn" @click="manualCheckin(item.id)">手动补签</button>
          </view>
        </view>
      </view>

      <!-- 缺勤列表 -->
      <view v-if="currentTab === 'absent'" class="list">
        <view v-if="absentList.length === 0" class="empty-list">暂无缺勤记录</view>
        <view v-else class="list-item" v-for="item in absentList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.name }}</text>
            <text class="student-id">{{ item.stu_num }}</text>
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
      activity_id: '',
      activity: null,
      checkedList: [],
      uncheckedList: [],
      absentList: [],
      loading: false,
      errorMsg: '',
      currentTab: 'checked'
    }
  },
  onLoad(options) {
    this.activity_id = options.activity_id;
    this.fetchData();
  },
  methods: {
    fetchData() {
      const token = uni.getStorageSync('teacher_token');
      if (!token) {
        this.errorMsg = '请先登录';
        uni.reLaunch({ url: '/pages/login/login' });
        return;
      }
      this.loading = true;
      this.errorMsg = '';
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: {
          action: 'get_activity_attendance',
          activity_id: this.activity_id,
          token
        },
        success: (res) => {
          this.loading = false;
          const data = res.data;
          if (data.code === 200) {
            this.activity = data.data.activity;
            this.checkedList = data.data.checked || [];
            this.uncheckedList = data.data.unchecked || [];
            this.absentList = data.data.absent || [];
          } else {
            this.errorMsg = data.msg || '获取考勤失败';
          }
        },
        fail: () => {
          this.loading = false;
          this.errorMsg = '网络错误，请重试';
        }
      });
    },
    formatTime(timeStr) {
      if (!timeStr) return '';
      const d = new Date(timeStr);
      const Y = d.getFullYear();
      const M = d.getMonth() + 1;
      const D = d.getDate();
      const h = String(d.getHours()).padStart(2, '0');
      const m = String(d.getMinutes()).padStart(2, '0');
      return `${Y}-${M}-${D} ${h}:${m}`;
    },
    manualCheckin(studentId) {
      uni.showModal({
        title: '手动补签',
        content: '确定将该学生标记为已签到吗？',
        success: (res) => {
          if (res.confirm) {
            const token = uni.getStorageSync('teacher_token');
            uni.request({
              url: 'http://8.148.31.127/class_checkin/teacher_api.php',
              method: 'POST',
              data: {
                action: 'manual_checkin',
                activity_id: this.activity_id,
                student_id: studentId,
                token
              },
              success: (res) => {
                if (res.data.code === 200) {
                  uni.showToast({ title: '补签成功' });
                  this.fetchData();
                } else {
                  uni.showToast({ title: res.data.msg || '操作失败', icon: 'none' });
                }
              }
            });
          }
        }
      });
    }
  }
}
</script>

<style scoped>
.detail-container { min-height: 100vh; background-color: #f5f7fa; padding: 30rpx; }
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 100rpx 0; }
.loading-icon { width: 60rpx; height: 60rpx; border: 4rpx solid #e9ecef; border-top-color: #007aff; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20rpx; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.error-tip { text-align: center; padding: 100rpx; color: #dc3545; font-size: 32rpx; }
.info-card { background: white; border-radius: 24rpx; padding: 30rpx; margin-bottom: 30rpx; }
.course-name { font-size: 40rpx; font-weight: bold; margin-bottom: 20rpx; }
.info-card text { display: block; font-size: 28rpx; color: #495057; margin-bottom: 10rpx; }
.stats-card { background: white; border-radius: 24rpx; padding: 30rpx; margin-bottom: 30rpx; display: flex; justify-content: space-around; }
.stat-item { text-align: center; }
.stat-number { display: block; font-size: 48rpx; font-weight: bold; color: #007aff; }
.stat-label { font-size: 26rpx; color: #868e96; }
.tabs { display: flex; background: white; border-radius: 24rpx; margin-bottom: 30rpx; overflow: hidden; }
.tab { flex: 1; text-align: center; padding: 30rpx 0; font-size: 28rpx; color: #6c757d; border-bottom: 4rpx solid transparent; }
.tab.active { color: #007aff; border-bottom-color: #007aff; }
.list { background: white; border-radius: 24rpx; overflow: hidden; }
.list-item { padding: 30rpx; border-bottom: 1rpx solid #f1f3f5; display: flex; justify-content: space-between; align-items: center; }
.item-info { flex: 2; }
.name { font-size: 32rpx; font-weight: bold; margin-right: 20rpx; }
.student-id { font-size: 26rpx; color: #6c757d; }
.item-time { flex: 1; text-align: right; }
.time { font-size: 24rpx; color: #adb5bd; display: block; }
.checkin-type { font-size: 24rpx; color: #28a745; }
.item-actions { flex: 1; text-align: right; }
.manual-checkin-btn { background: #28a745; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 26rpx; }
.empty-list { text-align: center; padding: 60rpx; color: #868e96; }
</style>