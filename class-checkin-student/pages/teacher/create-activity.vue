<template>
  <view class="create-container">
    <view class="form-card">
      <text class="page-title">创建签到活动</text>

      <!-- 1. 目标 WiFi -->
      <view class="section">
        <text class="section-title">1. 选择签到 WiFi <text class="required">*</text></text>
        <button class="scan-btn" @click="scanTargetWifi" :disabled="scanningTarget">{{ scanningTarget ? '扫描中...' : '扫描周围Wi‑Fi' }}</button>
        <view v-if="targetWifiList.length > 0" class="wifi-list">
          <view class="wifi-item" v-for="(wifi, idx) in targetWifiList" :key="idx"
                @click="selectTargetWifi(wifi)" :class="{ selected: selectedTarget && selectedTarget.BSSID === wifi.BSSID }">
            <text class="wifi-ssid">{{ wifi.SSID || '隐藏网络' }}</text>
            <text class="wifi-bssid">{{ wifi.BSSID }}</text>
          </view>
        </view>
      </view>

      <!-- 2. 环境指纹（可选） -->
      <view class="section">
        <text class="section-title">2. 教室环境指纹 <text class="optional">（可选）</text></text>
        <button class="scan-btn outline" @click="scanEnvFingerprint" :disabled="scanningEnv">{{ scanningEnv ? '扫描中...' : '采集周围Wi‑Fi' }}</button>
        <view v-if="envWifiList.length > 0" class="wifi-list">
          <view class="wifi-item" v-for="(wifi, idx) in envWifiList" :key="idx">
            <text class="wifi-ssid">{{ wifi.SSID || '隐藏网络' }}</text>
            <text class="wifi-bssid">{{ wifi.BSSID }}</text>
          </view>
        </view>
        <text class="tip" v-if="envWifiList.length > 0">已采集 {{ envWifiList.length }} 个热点</text>
      </view>

      <!-- 课程信息 -->
      <view class="form-item" v-if="course_name_from_param">
        <text class="label">所属课程</text>
        <view class="readonly-box">{{ course_name_from_param }}</view>
      </view>
      <view class="form-item" v-else>
        <text class="label">所属课程 <text class="required">*</text></text>
        <picker v-if="courses.length > 0" mode="selector" :range="courses" range-key="course_name" @change="onCourseChange">
          <view class="picker-display">{{ selectedCourseName || '请选择课程' }}</view>
        </picker>
        <view v-else class="picker-display disabled">暂无可选课程，请联系管理员分配</view>
      </view>

      <view class="form-item">
        <text class="label">WiFi 名称 (SSID)</text>
        <input type="text" v-model="wifi_ssid" class="input" readonly />
      </view>
      <view class="form-item">
        <text class="label">MAC 地址 (BSSID)</text>
        <input type="text" v-model="bssid" class="input" readonly />
      </view>
      <view class="form-item">
        <text class="label">WiFi 密码（选填）</text>
        <input type="text" v-model="wifi_password" class="input" />
      </view>

      <view class="form-item">
        <text class="label">当前位置</text>
        <view class="location-row">
          <text class="location-text">{{ locationText }}</text>
          <button class="location-btn" @click="getCurrentLocation">获取</button>
        </view>
      </view>

      <view class="form-item">
        <text class="label">开始时间 <text class="required">*</text></text>
        <view class="datetime-picker">
          <picker mode="date" :value="startDate" @change="onStartDateChange"><view class="picker-display time-box">{{ startDate }}</view></picker>
          <picker mode="time" :value="startTime" @change="onStartTimeChange"><view class="picker-display time-box">{{ startTime }}</view></picker>
        </view>
      </view>
      <view class="form-item">
        <text class="label">结束时间 <text class="required">*</text></text>
        <view class="datetime-picker">
          <picker mode="date" :value="endDate" @change="onEndDateChange"><view class="picker-display time-box">{{ endDate }}</view></picker>
          <picker mode="time" :value="endTime" @change="onEndTimeChange"><view class="picker-display time-box">{{ endTime }}</view></picker>
        </view>
      </view>

      <view class="form-item">
        <text class="label">位置验证半径 (米)</text>
        <input type="number" v-model.number="location_radius" class="input" />
      </view>
      <view class="form-item">
        <text class="label">AI 相似度阈值 (%)</text>
        <input type="number" v-model.number="similarity_threshold" class="input" />
      </view>

      <view class="btn-group">
        <button class="create-btn" @click="createActivity" :disabled="creating">创建活动</button>
        <button class="cancel-btn" @click="goBack">取消</button>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    const now = new Date();
    const today = this.formatDate(now);
    const nowTime = this.formatTime(now);
    const oneHourLater = this.formatTime(new Date(now.getTime() + 60 * 60 * 1000));
    return {
      scanningTarget: false,
      scanningEnv: false,
      targetWifiList: [],
      envWifiList: [],
      selectedTarget: null,
      courses: [],
      selectedCourseId: null,
      selectedCourseName: '',
      course_name_from_param: '',
      wifi_ssid: '',
      bssid: '',
      wifi_password: '',
      startDate: today,
      startTime: nowTime,
      endDate: today,
      endTime: oneHourLater,
      location_radius: 20,
      similarity_threshold: 40,
      latitude: null,
      longitude: null,
      locationText: '点击获取当前位置',
      creating: false,
      isTestEnv: false
    };
  },
  onLoad(options) {
    this.detectEnv();
    // 强制将course_id转为整数，避免字符串比较问题
    if (options.course_id && options.course_name) {
      this.selectedCourseId = parseInt(options.course_id) || 0;
      this.course_name_from_param = decodeURIComponent(options.course_name);
    } else {
      this.fetchCourses();
    }
  },
  methods: {
    detectEnv() { /* #ifdef H5 */ this.isTestEnv = true; /* #endif */ },
    formatDate(d) { return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; },
    formatTime(d) { return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`; },
    fetchCourses() {
      const token = uni.getStorageSync('teacher_token');
      if (!token) return;
      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        data: { action: 'get_my_courses', token },
        success: (res) => {
          if (res.data.code === 200) {
            this.courses = res.data.data;
            if (this.courses.length === 0) uni.showToast({ title: '您尚无课程', icon: 'none' });
          } else {
            uni.showToast({ title: res.data.msg || '获取课程失败', icon: 'none' });
          }
        },
        fail: () => uni.showToast({ title: '网络错误', icon: 'none' })
      });
    },
    onCourseChange(e) {
      const course = this.courses[e.detail.value];
      this.selectedCourseId = course.id;
      this.selectedCourseName = course.course_name;
    },
    getCurrentLocation() {
      uni.getLocation({
        type: 'wgs84',
        success: (res) => {
          this.latitude = res.latitude; this.longitude = res.longitude;
          this.locationText = `已获取 (${res.latitude.toFixed(5)})`;
        },
        fail: () => { this.locationText = '获取失败'; }
      });
    },
    // WiFi扫描方法（保持不变）
    scanTargetWifi() {
      if (this.scanningTarget) return;
      this.scanningTarget = true;
      this.targetWifiList = [];
      if (this.isTestEnv) {
        setTimeout(() => {
          this.targetWifiList = [
            { SSID: 'Campus-WiFi', BSSID: 'fa:ee:8c:17:ad:10', signalStrength: -44 },
            { SSID: 'Teacher-Hotspot', BSSID: 'aa:bb:cc:dd:ee:ff', signalStrength: -55 }
          ];
          this.scanningTarget = false;
        }, 800);
      } else {
        uni.startWifi({
          success: () => {
            uni.getWifiList({
              success: () => {
                uni.onGetWifiList((res) => {
                  this.targetWifiList = res.wifiList || [];
                  this.scanningTarget = false;
                });
              },
              fail: () => { this.scanningTarget = false; uni.showToast({ title: '获取WiFi列表失败', icon: 'none' }); }
            });
          },
          fail: () => { this.scanningTarget = false; uni.showToast({ title: 'WiFi模块未初始化', icon: 'none' }); }
        });
      }
    },
    selectTargetWifi(wifi) {
      this.selectedTarget = wifi;
      this.wifi_ssid = wifi.SSID || '隐藏网络';
      this.bssid = wifi.BSSID;
    },
    scanEnvFingerprint() {
      if (this.scanningEnv) return;
      this.scanningEnv = true;
      this.envWifiList = [];
      if (this.isTestEnv) {
        setTimeout(() => {
          this.envWifiList = [
            { SSID: 'Campus-WiFi', BSSID: 'fa:ee:8c:17:ad:10', signalStrength: -44 },
            { SSID: 'Library', BSSID: '11:22:33:44:55:66', signalStrength: -70 }
          ];
          this.scanningEnv = false;
        }, 800);
      } else {
        uni.startWifi({
          success: () => {
            uni.getWifiList({
              success: () => {
                uni.onGetWifiList((res) => {
                  this.envWifiList = res.wifiList || [];
                  this.scanningEnv = false;
                });
              },
              fail: () => { this.scanningEnv = false; uni.showToast({ title: '获取WiFi列表失败', icon: 'none' }); }
            });
          },
          fail: () => { this.scanningEnv = false; uni.showToast({ title: 'WiFi模块未初始化', icon: 'none' }); }
        });
      }
    },
    onStartDateChange(e) { this.startDate = e.detail.value; },
    onStartTimeChange(e) { this.startTime = e.detail.value; },
    onEndDateChange(e) { this.endDate = e.detail.value; },
    onEndTimeChange(e) { this.endTime = e.detail.value; },
    createActivity() {
      const courseId = this.selectedCourseId;
      if (!courseId) {
        uni.showToast({ title: '请选择或传入课程', icon: 'none' });
        return;
      }
      if (!this.wifi_ssid || !this.bssid) {
        uni.showToast({ title: '请扫描并选择签到WiFi', icon: 'none' });
        return;
      }
      // 时间校验：结束时间必须大于开始时间
      const startFull = `${this.startDate} ${this.startTime}:00`;
      const endFull = `${this.endDate} ${this.endTime}:00`;
      if (new Date(startFull) >= new Date(endFull)) {
        uni.showToast({ title: '结束时间必须晚于开始时间', icon: 'none' });
        return;
      }

      const token = uni.getStorageSync('teacher_token');
      if (!token) {
        uni.reLaunch({ url: '/pages/login/login' });
        return;
      }

      this.creating = true;
      uni.showLoading({ title: '创建中...' });

      const params = {
        action: 'create_activity',
        course_id: courseId,
        wifi_ssid: this.wifi_ssid,
        bssid: this.bssid,
        wifi_password: this.wifi_password,
        start_time: startFull,
        end_time: endFull,
        latitude: this.latitude,
        longitude: this.longitude,
        location_radius: this.location_radius,
        env_fingerprint: JSON.stringify(this.envWifiList),
        similarity_threshold: this.similarity_threshold / 100,
        token
      };
      console.log('创建活动参数:', params);  // 调试用

      uni.request({
        url: 'http://8.148.31.127/class_checkin/teacher_api.php',
        method: 'POST',
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        data: params,
        success: (res) => {
          uni.hideLoading();
          this.creating = false;
          console.log('创建活动响应:', res.data);
          if (res.data.code === 200) {
            uni.showToast({ title: '创建成功', icon: 'success' });
            setTimeout(() => uni.navigateBack(), 1500);
          } else {
            uni.showToast({ title: res.data.msg || '创建失败', icon: 'none', duration: 3000 });
          }
        },
        fail: (err) => {
          uni.hideLoading();
          this.creating = false;
          console.error('网络请求失败:', err);
          uni.showToast({ title: '网络连接失败，请检查网络', icon: 'none' });
        }
      });
    },
    goBack() { uni.navigateBack(); }
  }
};
</script>


<style scoped>
.create-container { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40rpx; }
.form-card { background: white; border-radius: 40rpx; padding: 40rpx; }
.page-title { font-size: 44rpx; font-weight: bold; text-align: center; margin-bottom: 40rpx; }
.section { margin-bottom: 40rpx; }
.section-title { font-size: 32rpx; font-weight: bold; margin-bottom: 20rpx; }
.required { color: #dc3545; }
.optional { color: #adb5bd; font-size: 24rpx; margin-left: 10rpx; }
.scan-btn { width: 100%; background: #007aff; color: white; border-radius: 40rpx; font-size: 30rpx; height: 80rpx; line-height: 80rpx; margin-bottom: 20rpx; }
.scan-btn.outline { background: #f0f4ff; color: #007aff; border: 2rpx solid #007aff; }
.wifi-list { max-height: 360rpx; overflow-y: auto; border: 2rpx solid #e9ecef; border-radius: 20rpx; }
.wifi-item { padding: 20rpx 30rpx; border-bottom: 1rpx solid #f1f3f5; }
.wifi-item.selected { background-color: #e7f5ff; border-left: 6rpx solid #007aff; }
.wifi-ssid { font-size: 30rpx; font-weight: bold; display: block; }
.wifi-bssid { font-size: 24rpx; color: #6c757d; margin-top: 6rpx; }
.form-item { margin-bottom: 30rpx; }
.label { font-size: 30rpx; color: #495057; margin-bottom: 10rpx; }
.input { background: #f5f5f5; border: 2rpx solid #e9ecef; border-radius: 20rpx; padding: 25rpx 30rpx; font-size: 30rpx; width: 100%; box-sizing: border-box; }
.readonly-box { background: #f5f5f5; border-radius: 20rpx; padding: 25rpx 30rpx; font-size: 30rpx; color: #333; }
.picker-display { background: #f5f5f5; border: 2rpx solid #e9ecef; border-radius: 20rpx; padding: 25rpx 30rpx; font-size: 30rpx; color: #333; }
.picker-display.disabled { color: #adb5bd; }
.location-row { display: flex; align-items: center; background: #f5f5f5; border: 2rpx solid #e9ecef; border-radius: 20rpx; padding: 10rpx 10rpx 10rpx 30rpx; }
.location-text { flex: 1; font-size: 28rpx; color: #495057; }
.location-btn { background: #007aff; color: white; font-size: 28rpx; padding: 15rpx 30rpx; border-radius: 30rpx; }
.datetime-picker { display: flex; gap: 20rpx; }
.time-box { flex: 1; text-align: center; }
.tip { font-size: 24rpx; color: #007aff; margin-top: 10rpx; }
.btn-group { margin-top: 20rpx; }
.create-btn { background: #007aff; color: white; font-size: 36rpx; height: 90rpx; line-height: 90rpx; border-radius: 45rpx; margin-bottom: 20rpx; }
.cancel-btn { background: #f8f9fa; color: #6c757d; font-size: 36rpx; height: 90rpx; line-height: 90rpx; border-radius: 45rpx; border: 2rpx solid #dee2e6; }
</style>