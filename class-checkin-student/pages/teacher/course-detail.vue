<template>
  <view class="container">
    <view class="header">
      <text class="title">{{ courseName }}</text>
      <button class="create-btn" @click="createActivity">+ 创建活动</button>
    </view>
    <view class="tabs">
      <view class="tab" :class="{active: tabIndex===0}" @click="tabIndex=0">进行中 ({{ activeList.length }})</view>
      <view class="tab" :class="{active: tabIndex===1}" @click="tabIndex=1">历史 ({{ historyList.length }})</view>
    </view>
    <view v-if="fetchError" class="error-tip">{{ fetchError }}</view>
    <view v-else-if="loading" class="loading">加载中...</view>
    <view v-else-if="currentList.length === 0" class="empty">暂无相关活动</view>
    <view v-else class="activity-list">
      <view class="activity-item" v-for="item in currentList" :key="item.id" @click="viewDetail(item.id)">
        <view class="activity-header">
          <text class="course-name">{{ item.course_name }}</text>
          <text class="activity-status" :class="item.statusClass">{{ item.statusText }}</text>
        </view>
        <view class="activity-info">
          <text>📶 {{ item.wifi_ssid }}</text>
          <text>⏰ {{ item.start_time_format }} - {{ item.end_time_format }}</text>
          <text>📍 {{ item.location || '未设置' }}</text>
          <text>👥 已签到：{{ item.checkin_count || 0 }} 人</text>
        </view>
        <view class="activity-footer">
          <text class="view-detail">查看详情 ›</text>
          <button v-if="item.isActive" class="end-btn" @click.stop="endActivity(item.id)">提前结束</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      courseId: 0,
      courseName: '',
      activities: [],
      loading: false,
      tabIndex: 0,
      fetchError: ''
    }
  },
  computed: {
    activeList() {
      return this.activities.filter(a => a.statusText === '进行中' || a.statusText === '未开始')
    },
    historyList() {
      return this.activities.filter(a => a.statusText === '已结束')
    },
    currentList() {
      return this.tabIndex === 0 ? this.activeList : this.historyList
    }
  },
  onLoad(options) {
    this.courseId = parseInt(options.course_id) || 0;
    this.courseName = decodeURIComponent(options.course_name || '');
    if (this.courseId === 0) {
      this.fetchError = '课程信息无效';
      return;
    }
    this.fetchActivities();
  },
  methods: {
    fetchActivities() {
      const token = uni.getStorageSync('teacher_token');
      if (!token) {
        uni.reLaunch({ url: '/pages/login/login' });
        return;
      }
      this.loading = true;
      this.fetchError = '';
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { 
          action: 'get_my_activities', 
          course_id: this.courseId,
          token 
        },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.loading = false;
          if (res.data.code === 200) {
            this.activities = (res.data.data || []).map(item => this.processItem(item));
          } else {
            this.fetchError = res.data.msg || '获取活动失败';
          }
        },
        fail: () => {
          this.loading = false;
          this.fetchError = '网络连接失败';
        }
      });
    },
    processItem(item) {
      const now = new Date();
      const start = new Date(item.start_time);
      const end = new Date(item.end_time);
      let statusText = '', statusClass = '', isActive = false;
      if (now < start) { statusText = '未开始'; statusClass = 'status-pending'; }
      else if (now > end) { statusText = '已结束'; statusClass = 'status-ended'; }
      else { statusText = '进行中'; statusClass = 'status-active'; isActive = true; }
      return { ...item, statusText, statusClass, isActive,
        start_time_format: item.start_time?.replace('T', ' ').substring(0, 16) || '',
        end_time_format: item.end_time?.replace('T', ' ').substring(0, 16) || ''
      };
    },
    createActivity() {
      uni.navigateTo({
        url: `/pages/teacher/create-activity?course_id=${this.courseId}&course_name=${encodeURIComponent(this.courseName)}`
      });
    },
    viewDetail(id) {
      uni.navigateTo({ url: `/pages/teacher/activity-detail?activity_id=${id}` });
    },
    endActivity(id) {
      uni.showModal({
        title: '提示', content: '确定提前结束吗？',
        success: (res) => {
          if (res.confirm) {
            const token = uni.getStorageSync('teacher_token');
            uni.request({
              url: 'http://8.148.31.127/class_checkin/teacher_api.php',
              method: 'POST',
              data: { action: 'end_activity', activity_id: id, token },
              header: { 'Content-Type': 'application/x-www-form-urlencoded' },
              success: (res) => {
                if (res.data.code === 200) {
                  uni.showToast({ title: '已结束' });
                  this.fetchActivities();
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
.container { min-height:100vh; background:#f5f7fa; padding:30rpx; }
.header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:20rpx; }
.title{ font-size:40rpx; font-weight:bold; }
.create-btn{ background:#007aff; color:#fff; padding:10rpx 30rpx; border-radius:30rpx; font-size:28rpx; }
.tabs{ display:flex; background:#fff; border-radius:20rpx; margin-bottom:20rpx; padding:10rpx; }
.tab{ flex:1; text-align:center; padding:20rpx; border-radius:16rpx; font-size:28rpx; color:#6c757d; }
.tab.active{ background:#007aff; color:#fff; }
.error-tip{ text-align:center; padding:100rpx 0; color:#dc3545; }
.loading,.empty{ text-align:center; padding:100rpx 0; color:#868e96; }
.activity-item{ background:#fff; border-radius:24rpx; padding:30rpx; margin-bottom:20rpx; }
.activity-header{ display:flex; justify-content:space-between; margin-bottom:20rpx; }
.course-name{ font-size:36rpx; font-weight:bold; }
.activity-status{ padding:6rpx 16rpx; border-radius:30rpx; font-size:24rpx; }
.status-active{ background:#d4edda; color:#155724; }
.status-pending{ background:#fff3cd; color:#856404; }
.status-ended{ background:#e9ecef; color:#6c757d; }
.activity-info text{ display:block; font-size:28rpx; color:#495057; margin-bottom:10rpx; }
.activity-footer{ display:flex; justify-content:space-between; align-items:center; margin-top:20rpx; }
.view-detail{ color:#007aff; }
.end-btn{ background:#dc3545; color:#fff; padding:10rpx 30rpx; border-radius:30rpx; font-size:26rpx; }
</style>