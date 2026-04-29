<template>
  <view class="container">
    <view class="header">
      <text class="title">课堂签到</text>
      <text class="greeting">{{ userInfo.name || '' }}，你好</text>
    </view>

    <!-- 课程筛选（仅当有课程时显示） -->
    <view class="course-filter" v-if="courses.length > 0">
      <picker
        mode="selector"
        :range="courseOptions"
        range-key="course_name"
        @change="onCourseChange"
      >
        <view class="picker-display">
          <text>{{ selectedCourseName }}</text>
          <text class="arrow">▼</text>
        </view>
      </picker>
    </view>

    <!-- 活动标签页 -->
    <view class="activity-tabs">
      <view class="tab" :class="{ active: tabIndex === 0 }" @click="tabIndex = 0">
        进行中 ({{ activeCount }})
      </view>
      <view class="tab" :class="{ active: tabIndex === 1 }" @click="tabIndex = 1">
        历史 ({{ historyCount }})
      </view>
    </view>

    <view v-if="loading" class="loading-state">加载中...</view>
    <view v-else-if="errorMsg" class="error-tip">{{ errorMsg }}</view>
    <view v-else-if="courses.length === 0" class="empty-state">
      您还没有选修任何课程，请联系管理员。
    </view>
    <view v-else>
      <view v-if="currentList.length === 0" class="empty-state">
        {{ tabIndex === 0 ? '暂无进行中的活动' : '暂无历史活动' }}
      </view>
      <view v-else class="activity-list">
        <view class="activity-item" v-for="item in currentList" :key="item.id">
          <view class="course-header">
            <text class="course-name">{{ item.course_name }}</text>
            <text class="course-status" :class="item.status">
              {{ statusMap[item.status] }}
            </text>
          </view>
          <view class="course-info">
            <view class="info-row">
              <text class="label">👨‍🏫</text>
              <text class="value">{{ item.teacher_name }}</text>
            </view>
            <view class="info-row">
              <text class="label">📍</text>
              <text class="value">{{ item.location || '未设置' }}</text>
            </view>
            <view class="info-row">
              <text class="label">⏰</text>
              <text class="value">
                {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}
              </text>
            </view>
            <view class="info-row wifi">
              <text class="label">📶</text>
              <text class="value">{{ item.wifi_ssid }}</text>
            </view>
          </view>
          <view v-if="item.has_checked" class="checked-tag">✅ 已签到</view>
          <button
            v-else-if="item.status === 'active'"
            class="checkin-btn"
            @click="goCheckin(item.id)"
          >
            立即签到
          </button>
          <button v-else class="disabled-btn" disabled>
            {{ item.status === 'pending' ? '未开始' : '已结束' }}
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      userInfo: {},
      courses: [],
      selectedCourseId: 0,
      activities: [],
      loading: false,
      tabIndex: 0,
      errorMsg: '',
      statusMap: { pending: '未开始', active: '进行中', ended: '已结束' },
    };
  },
  computed: {
    // 将课程列表转换为 picker 可用的对象数组（包含 course_name 字段）
    courseOptions() {
      return [{ course_id: 0, course_name: '全部课程' }, ...this.courses];
    },
    selectedCourseName() {
      if (this.selectedCourseId === 0) return '全部课程';
      const course = this.courses.find((c) => c.id === this.selectedCourseId);
      return course ? course.course_name : '全部课程';
    },
    activeCount() {
      return this.activities.filter(
        (a) => a.status === 'active' || a.status === 'pending'
      ).length;
    },
    historyCount() {
      return this.activities.filter(
        (a) => a.status === 'ended' || a.has_checked
      ).length;
    },
    currentList() {
      if (this.tabIndex === 0) {
        return this.activities.filter(
          (a) => a.status === 'active' || a.status === 'pending'
        );
      }
      return this.activities.filter(
        (a) => a.status === 'ended' || a.has_checked
      );
    },
  },
  onShow() {
    this.userInfo = uni.getStorageSync('user') || {};
    this.fetchCourses();
  },
  methods: {
    fetchCourses() {
      const token = uni.getStorageSync('token');
      if (!token) return uni.reLaunch({ url: '/pages/login/login' });
      this.errorMsg = '';
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_courses', token },
        success: (res) => {
          if (res.data.code === 200) {
            this.courses = res.data.data; // 后端返回的课程对象已包含 id, course_name 等字段
            this.fetchActivities();
          } else {
            this.errorMsg = res.data.msg || '获取课程失败';
          }
        },
        fail: () => {
          this.errorMsg = '网络异常';
        },
      });
    },
    fetchActivities() {
      this.loading = true;
      this.errorMsg = '';
      const token = uni.getStorageSync('token');
      const data = { action: 'get_activities', token };
      if (this.selectedCourseId > 0) data.course_id = this.selectedCourseId;
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data,
        success: (res) => {
          this.loading = false;
          if (res.data.code === 200) {
            this.activities = res.data.data;
          } else {
            this.errorMsg = res.data.msg || '加载活动失败';
          }
        },
        fail: () => {
          this.loading = false;
          this.errorMsg = '网络异常';
        },
      });
    },
    onCourseChange(e) {
      // picker 返回选中的索引，通过索引从 courseOptions 中取出对应课程
      const selected = this.courseOptions[e.detail.value];
      this.selectedCourseId = selected.course_id || selected.id || 0;
      this.fetchActivities();
    },
    formatTime(t) {
      if (!t) return '';
      const d = new Date(t);
      const month = d.getMonth() + 1;
      const day = d.getDate();
      const h = String(d.getHours()).padStart(2, '0');
      const m = String(d.getMinutes()).padStart(2, '0');
      return `${month}-${day} ${h}:${m}`;
    },
    goCheckin(id) {
      uni.navigateTo({ url: `/pages/checkin/checkin?activity_id=${id}` });
    },
  },
};
</script>




<style scoped>
.container { min-height:100vh; background:#f5f7fa; padding:30rpx; padding-bottom:100rpx; }
.header { margin-bottom:30rpx; }
.title { font-size:48rpx; font-weight:bold; }
.greeting { display:block; font-size:28rpx; color:#6c757d; margin-top:10rpx; }
.course-filter { background:#fff; border-radius:20rpx; padding:20rpx 30rpx; margin-bottom:20rpx; }
.picker-display { display:flex; justify-content:space-between; font-size:30rpx; }
.arrow { color:#adb5bd; }
.activity-tabs { display:flex; background:#fff; border-radius:20rpx; margin-bottom:20rpx; padding:10rpx; }
.tab { flex:1; text-align:center; padding:20rpx; border-radius:16rpx; font-size:28rpx; color:#6c757d; }
.tab.active { background:#007aff; color:#fff; }
.loading-state { display:flex; flex-direction:column; align-items:center; padding:100rpx 0; }
.loading-icon { width:60rpx; height:60rpx; border:4rpx solid #e9ecef; border-top-color:#007aff; border-radius:50%; animation:spin 1s linear infinite; margin-bottom:20rpx; }
@keyframes spin { 0%{ transform:rotate(0deg); } 100%{ transform:rotate(360deg); } }
.error-tip { text-align:center; padding:100rpx; color:#dc3545; }
.empty-state { text-align:center; padding:200rpx 0; color:#868e96; }
.activity-item { background:#fff; border-radius:24rpx; padding:30rpx; margin-bottom:30rpx; }
.course-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20rpx; }
.course-name { font-size:36rpx; font-weight:bold; }
.course-status { font-size:24rpx; padding:6rpx 16rpx; border-radius:30rpx; }
.course-status.pending { background:#fff3cd; color:#856404; }
.course-status.active { background:#d4edda; color:#155724; }
.course-status.ended { background:#e9ecef; color:#6c757d; }
.course-info { background:#f8f9fa; border-radius:16rpx; padding:20rpx; margin:20rpx 0; }
.info-row { margin-bottom:12rpx; display:flex; }
.label { width:120rpx; color:#868e96; font-size:28rpx; }
.value { flex:1; color:#212529; font-size:28rpx; }
.wifi-row { background:#e7f5ff; margin:-10rpx -10rpx -10rpx 0; padding:10rpx; border-radius:12rpx; }
.checked-container { display:flex; align-items:center; justify-content:center; }
.checked-tag { background:#28a745; color:#fff; padding:10rpx 30rpx; border-radius:40rpx; font-size:28rpx; }
.button-group { display:flex; gap:20rpx; margin-top:20rpx; }
.checkin-btn { flex:1; background:#007aff; color:#fff; font-size:32rpx; height:80rpx; line-height:80rpx; border-radius:40rpx; border:none; }
.disabled-btn { flex:1; background:#e9ecef; color:#6c757d; font-size:32rpx; height:80rpx; line-height:80rpx; border-radius:40rpx; border:none; }
</style>