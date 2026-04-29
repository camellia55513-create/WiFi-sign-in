<template>
  <view class="content">
    <view class="tips">
      <text class="title">签到确认</text>
      <text class="wifi-info">目标WiFi：{{ wifiName }}</text>
    </view>

    <!-- 最新扫描结果展示 -->
    <view class="scan-section" v-if="lastWifiList.length > 0">
      <text class="section-title">附近Wi-Fi列表</text>
      <view class="wifi-item" v-for="wifi in lastWifiList" :key="wifi.BSSID">
        <text class="ssid">{{ wifi.SSID || '隐藏网络' }}</text>
        <text class="bssid">{{ wifi.BSSID }}</text>
        <text class="signal">{{ wifi.signalStrength }} dBm</text>
      </view>
    </view>

    <!-- 扫描进度 -->
    <view class="scan-progress" v-if="scanning">
      <text>正在扫描WiFi ({{ scanCount }}/{{ totalScans }})</text>
      <view class="progress-bar">
        <view class="fill" :style="{ width: (scanCount / totalScans) * 100 + '%' }"></view>
      </view>
    </view>

    <!-- 按钮区 -->
    <view class="button-area">
      <button type="primary" @click="startScan" :disabled="scanning || checking || needPermission">
        {{ scanning ? '扫描中...' : (checking ? '签到中...' : '开始签到') }}
      </button>
      <button class="back-btn" @click="goBack">返回</button>
    </view>

    <view v-if="isTestEnv" class="test-tip">🧪 测试环境</view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      activityId: '',
      wifiName: '',
      bssid: '',
      totalScans: 3,
      scanCount: 0,
      scans: [],
      lastWifiList: [],
      scanning: false,
      checking: false,
      isTestEnv: false,
      timer: null,
      needPermission: false,
      wifiListener: null,
      latitude: null,
      longitude: null
    }
  },
  onLoad(options) {
    this.activityId = options.activity_id;
    this.detectEnv();
    this.checkPermission();
    this.getActivityInfo();
    this.getLocation();
  },
  onUnload() { this.clearTimer(); this.removeWifiListener(); },
  methods: {
    detectEnv() { /* #ifdef H5 */ this.isTestEnv = true; /* #endif */ },
    checkPermission() {
      // #ifdef MP-WEIXIN
      wx.getSetting({
        success: (res) => {
          if (!res.authSetting['scope.userLocation']) {
            this.needPermission = true;
          }
        }
      })
      // #endif
    },
    requestPermission() {
      // #ifdef MP-WEIXIN
      wx.authorize({
        scope: 'scope.userLocation',
        success: () => { this.needPermission = false; this.getLocation(); },
        fail: () => { uni.showModal({ title: '提示', content: '需要位置权限才能扫描WiFi', showCancel: false }); }
      })
      // #endif
    },
    getLocation() {
      uni.getLocation({
        type: 'wgs84',
        success: (res) => { this.latitude = res.latitude; this.longitude = res.longitude; },
        fail: () => {}
      });
    },
    getActivityInfo() {
      const token = uni.getStorageSync('token');
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: { action: 'get_activity_detail', activity_id: this.activityId, token },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          if (res.data.code === 200) {
            this.wifiName = res.data.data.wifi_ssid;
            this.bssid = res.data.data.bssid;
          }
        }
      });
    },
    startScan() {
      if (this.scanning || this.needPermission) return;
      this.scanCount = 0; this.scans = []; this.lastWifiList = [];
      this.scanning = true; this.removeWifiListener(); this.nextScan();
    },
    nextScan() {
      if (this.scanCount >= this.totalScans) {
        this.scanning = false;
        this.removeWifiListener();
        this.submitCheckin();
        return;
      }
      if (this.isTestEnv) {
        setTimeout(() => {
          this.handleScanResult([
            { BSSID: this.bssid, SSID: this.wifiName, signalStrength: -55 },
            { BSSID: '11:22:33:44:55:66', SSID: 'Other', signalStrength: -70 }
          ]);
        }, 500);
      } else {
        uni.startWifi({
          success: () => {
            uni.getWifiList({
              success: () => {
                this.wifiListener = (res) => { this.handleScanResult(res.wifiList); this.removeWifiListener(); };
                uni.onGetWifiList(this.wifiListener);
              },
              fail: () => { this.scanning = false; uni.showToast({ title: '获取WiFi列表失败', icon: 'none' }); }
            });
          },
          fail: () => { this.scanning = false; uni.showToast({ title: 'WiFi初始化失败', icon: 'none' }); }
        });
      }
    },
    handleScanResult(wifiList) {
      this.scans.push(wifiList);
      this.lastWifiList = wifiList;
      this.scanCount++;
      this.timer = setTimeout(() => this.nextScan(), 1000);
    },
    submitCheckin() {
      if (this.checking) return;
      this.checking = true;
      const scansData = [];
      this.scans.forEach((list, idx) => {
        list.forEach(w => {
          let rssi = w.signalStrength !== undefined ? w.signalStrength : (w.RSSI || -100);
          if (rssi > 0) rssi = -rssi;
          scansData.push({
            bssid: (w.BSSID || '').toUpperCase().replace(/:/g, ''),
            ssid: w.SSID || '',
            rssi: rssi,
            scan_round: idx + 1
          });
        });
      });
      const token = uni.getStorageSync('token');
      uni.request({
        url: 'http://8.148.31.127/class_checkin/api.php',
        method: 'POST',
        data: {
          action: 'confirm_checkin',
          activity_id: this.activityId,
          scans: JSON.stringify(scansData),
          device_info: uni.getSystemInfoSync().model || 'unknown',
          latitude: this.latitude,
          longitude: this.longitude,
          token
        },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          this.checking = false;
          if (res.data.code === 200) {
            uni.redirectTo({ url: '/pages/success/success' });
          } else {
            uni.showToast({ title: res.data.msg || '签到失败', icon: 'none' });
          }
        },
        fail: () => { this.checking = false; uni.showToast({ title: '网络错误', icon: 'none' }); }
      });
    },
    removeWifiListener() { if (this.wifiListener) { uni.offGetWifiList(this.wifiListener); this.wifiListener = null; } },
    clearTimer() { if (this.timer) { clearTimeout(this.timer); this.timer = null; } },
    goBack() { uni.navigateBack(); }
  }
}
</script>



<style scoped>
.content {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40rpx;
  display: flex;
  flex-direction: column;
}
.tips {
  background: rgba(255,255,255,0.95);
  border-radius: 24rpx;
  padding: 60rpx 40rpx;
  margin-bottom: 30rpx;
  text-align: center;
}
.title {
  display: block;
  font-size: 48rpx;
  font-weight: bold;
  margin-bottom: 20rpx;
}
.wifi-info {
  font-size: 32rpx;
  background: #e7f5ff;
  padding: 20rpx;
  border-radius: 20rpx;
}
.scan-section {
  background: #fff;
  border-radius: 20rpx;
  padding: 30rpx;
  margin-bottom: 20rpx;
}
.section-title {
  font-size: 28rpx;
  color: #495057;
  margin-bottom: 20rpx;
  font-weight: bold;
}
.wifi-item {
  display: flex;
  align-items: center;
  padding: 15rpx 0;
  border-bottom: 1rpx solid #f1f3f5;
}
.ssid {
  flex: 2;
  font-size: 28rpx;
  font-weight: bold;
}
.bssid {
  flex: 2;
  font-size: 24rpx;
  color: #6c757d;
  text-align: center;
}
.signal {
  flex: 1;
  font-size: 26rpx;
  color: #28a745;
  text-align: right;
}
.scan-progress {
  background: #fff;
  border-radius: 20rpx;
  padding: 30rpx;
  margin-bottom: 30rpx;
}
.progress-bar {
  height: 20rpx;
  background: #e9ecef;
  border-radius: 10rpx;
  margin-top: 15rpx;
}
.fill {
  height: 100%;
  background: #007aff;
  border-radius: 10rpx;
  transition: width 0.3s;
}
.button-area {
  margin-top: auto;
}
button[type="primary"] {
  background: #007aff;
  color: #fff;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 45rpx;
  font-size: 36rpx;
  margin-bottom: 20rpx;
}
.back-btn {
  background: #6c757d;
  color: #fff;
  height: 90rpx;
  line-height: 90rpx;
  border-radius: 45rpx;
  font-size: 36rpx;
}
.test-tip {
  background: #e7f5ff;
  border-radius: 16rpx;
  padding: 20rpx;
  margin-top: 20rpx;
  text-align: center;
}
</style>