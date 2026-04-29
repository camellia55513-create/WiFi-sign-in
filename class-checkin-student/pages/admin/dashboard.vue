<template>
  <view class="container">
    <view class="header">
      <text class="title">管理后台</text>
      <button class="logout-btn" @click="logout">退出</button>
    </view>

    <!-- 标签页切换 -->
    <view class="tabs">
      <view class="tab" :class="{ active: currentTab === 'students' }" @click="currentTab = 'students'">
        学生管理
      </view>
      <view class="tab" :class="{ active: currentTab === 'teachers' }" @click="currentTab = 'teachers'">
        教师管理
      </view>
      <view class="tab" :class="{ active: currentTab === 'courses' }" @click="currentTab = 'courses'">
        课程管理
      </view>
      <view class="tab" :class="{ active: currentTab === 'enrollment' }" @click="currentTab = 'enrollment'">
        选课管理
      </view>
    </view>

    <!-- ========== 学生管理 ========== -->
    <view v-if="currentTab === 'students'">
      <view class="action-bar">
        <button class="import-btn" @click="showImportStudent = true">+ 批量导入学生</button>
      </view>
      <view v-if="studentLoading" class="loading-state">加载中...</view>
      <view v-else-if="studentList.length === 0" class="empty-state">暂无学生数据</view>
      <view v-else class="list">
        <view class="list-item" v-for="item in studentList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.name }}</text>
            <text class="id">学号：{{ item.student_id }}</text>
          </view>
          <button class="delete-btn" @click="deleteStudent(item.id)">删除</button>
        </view>
      </view>
    </view>

    <!-- ========== 教师管理 ========== -->
    <view v-if="currentTab === 'teachers'">
      <view class="action-bar">
        <button class="import-btn" @click="showImportTeacher = true">+ 批量导入教师</button>
      </view>
      <view v-if="teacherLoading" class="loading-state">加载中...</view>
      <view v-else-if="teacherList.length === 0" class="empty-state">暂无教师数据</view>
      <view v-else class="list">
        <view class="list-item" v-for="item in teacherList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.name }}</text>
            <text class="id">工号：{{ item.teacher_id }}</text>
          </view>
          <button class="delete-btn" @click="deleteTeacher(item.id)">删除</button>
        </view>
      </view>
    </view>

    <!-- ========== 课程管理 ========== -->
    <view v-if="currentTab === 'courses'">
      <view class="action-bar">
        <button class="import-btn" @click="showCourseModal = true">+ 新建课程</button>
      </view>
      <view v-if="courseLoading" class="loading-state">加载中...</view>
      <view v-else-if="courseList.length === 0" class="empty-state">暂无课程数据</view>
      <view v-else class="list">
        <view class="list-item" v-for="item in courseList" :key="item.id">
          <view class="item-info">
            <text class="name">{{ item.course_name }}</text>
            <text class="id">授课教师：{{ item.teacher_name || '未分配' }}</text>
            <text class="id">地点：{{ item.location || '未设置' }}</text>
          </view>
          <view class="item-actions">
            <button class="edit-btn" @click="editCourse(item)">编辑</button>
            <button class="delete-btn" @click="deleteCourse(item.id)">删除</button>
          </view>
        </view>
      </view>
    </view>

    <!-- ========== 选课管理 ========== -->
    <view v-if="currentTab === 'enrollment'">
      <view class="enrollment-container">
        <!-- 选择课程 -->
        <view class="selector-row">
          <picker mode="selector" :range="courseOptionsForSelect" range-key="course_name" @change="onSelectCourse">
            <view class="picker-box">{{ selectedCourseName || '选择课程' }}</view>
          </picker>
        </view>

        <!-- 添加方式切换 -->
        <view class="mode-switch">
          <view class="mode-tab" :class="{ active: addMode === 'single' }" @click="addMode = 'single'">单个添加</view>
          <view class="mode-tab" :class="{ active: addMode === 'batch' }" @click="addMode = 'batch'">批量添加</view>
        </view>

        <!-- 单个添加 -->
        <view v-if="addMode === 'single'" class="single-add">
          <picker mode="selector" :range="studentOptions" range-key="label" @change="onSelectStudent">
            <view class="picker-box">{{ selectedStudentLabel || '选择学生' }}</view>
          </picker>
          <button class="add-btn" @click="addStudentToCourse">添加</button>
        </view>

        <!-- 批量添加 -->
        <view v-else class="batch-add">
          <view class="batch-tip">每行输入一个学号，支持批量</view>
          <textarea v-model="batchStudentIds" placeholder="例如：&#10;2022001&#10;2022002&#10;2022003" class="batch-textarea" />
          <button class="add-btn batch-btn" @click="batchAddStudents">批量添加</button>
        </view>

        <!-- 已选学生列表 -->
        <view class="enrolled-list">
          <view class="list-header">
            <text>已选该课程的学生 ({{ enrolledStudents.length }})</text>
            <button class="refresh-btn" @click="fetchEnrolledStudents">刷新</button>
          </view>
          <view v-if="enrolledStudents.length === 0" class="empty-tip">暂无学生，请先添加</view>
          <view v-else class="list-item" v-for="s in enrolledStudents" :key="s.id">
            <text>{{ s.name }} ({{ s.student_id }})</text>
            <button class="remove-btn" @click="removeStudentFromCourse(s.id)">移除</button>
          </view>
        </view>
      </view>
    </view>

    <!-- 导入学生弹窗 -->
    <view class="modal" v-if="showImportStudent" @click="showImportStudent = false">
      <view class="modal-content" @click.stop>
        <view class="modal-title">批量导入学生</view>
        <view class="modal-tip">每行格式：学号,姓名</view>
        <textarea v-model="studentData" placeholder="2022001,张三&#10;2022002,李四" class="modal-textarea" />
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="showImportStudent = false">取消</button>
          <button class="modal-btn confirm" @click="importStudents">导入</button>
        </view>
      </view>
    </view>

    <!-- 导入教师弹窗 -->
    <view class="modal" v-if="showImportTeacher" @click="showImportTeacher = false">
      <view class="modal-content" @click.stop>
        <view class="modal-title">批量导入教师</view>
        <view class="modal-tip">每行格式：工号,姓名</view>
        <textarea v-model="teacherData" placeholder="1002,杨老师&#10;1003,李老师" class="modal-textarea" />
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="showImportTeacher = false">取消</button>
          <button class="modal-btn confirm" @click="importTeachers">导入</button>
        </view>
      </view>
    </view>

    <!-- 课程弹窗（新建/编辑） -->
    <view class="modal" v-if="showCourseModal" @click="showCourseModal = false">
      <view class="modal-content" @click.stop>
        <view class="modal-title">{{ editingCourse ? '编辑课程' : '新建课程' }}</view>
        <input v-model="courseForm.course_name" placeholder="课程名称" class="modal-input" />
        <input v-model="courseForm.location" placeholder="上课地点（如：教学楼A-101）" class="modal-input" />
        <picker mode="selector" :range="teacherOptions" range-key="name" @change="onTeacherChange">
          <view class="picker-display">{{ selectedTeacherName || '选择授课教师' }}</view>
        </picker>
        <view class="modal-buttons">
          <button class="modal-btn cancel" @click="showCourseModal = false">取消</button>
          <button class="modal-btn confirm" @click="saveCourse">保存</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      currentTab: 'students',
      // 学生
      studentList: [],
      studentLoading: false,
      showImportStudent: false,
      studentData: '',
      // 教师
      teacherList: [],
      teacherLoading: false,
      showImportTeacher: false,
      teacherData: '',
      // 课程
      courseList: [],
      courseLoading: false,
      showCourseModal: false,
      editingCourse: null,
      courseForm: { course_name: '', location: '', teacher_id: '' },
      teacherOptions: [],
      selectedTeacherName: '',
      // 选课
      addMode: 'single',            // single 或 batch
      studentOptions: [],
      selectedStudentId: null,
      selectedStudentLabel: '',
      courseOptionsForSelect: [],
      selectedCourseId: null,
      selectedCourseName: '',
      enrolledStudents: [],
      batchStudentIds: ''           // 批量添加的学号文本
    }
  },
  watch: {
    currentTab(val) {
      if (val === 'students') this.fetchStudents()
      else if (val === 'teachers') this.fetchTeachers()
      else if (val === 'courses') {
        this.fetchTeachers()
        this.fetchCourses()
      } else if (val === 'enrollment') {
        this.loadEnrollmentData()
      }
    }
  },
  onShow() {
    this.checkLogin()
    this.fetchStudents()
    this.fetchTeachers()
  },
  methods: {
    checkLogin() {
      const token = uni.getStorageSync('admin_token')
      if (!token) uni.reLaunch({ url: '/pages/login/login' })
    },
    logout() {
      uni.showModal({
        title: '提示',
        content: '确定退出吗？',
        success: (res) => {
          if (res.confirm) {
            uni.removeStorageSync('admin_token')
            uni.reLaunch({ url: '/pages/login/login' })
          }
        }
      })
    },
    // 通用请求
    request(action, data = {}, callback, showError = true) {
      const token = uni.getStorageSync('admin_token')
      uni.request({
        url: 'http://8.148.31.127/class_checkin/admin_api.php',
        method: 'POST',
        data: { action, token, ...data },
        header: { 'Content-Type': 'application/x-www-form-urlencoded' },
        success: (res) => {
          if (res.data.code === 200) callback(res.data)
          else if (showError) uni.showToast({ title: res.data.msg || '操作失败', icon: 'none' })
        },
        fail: () => uni.showToast({ title: '网络错误', icon: 'none' })
      })
    },

    // ---------- 学生 ----------
    fetchStudents() {
      this.studentLoading = true
      this.request('list_students', {}, (data) => {
        this.studentList = data.data || []
        this.studentLoading = false
      })
    },
    deleteStudent(id) {
      uni.showModal({
        title: '确认删除',
        content: '删除后不可恢复，确定吗？',
        success: (res) => {
          if (res.confirm) {
            this.request('delete_student', { id }, () => {
              uni.showToast({ title: '删除成功', icon: 'success' })
              this.fetchStudents()
            })
          }
        }
      })
    },
    parseLines(text, key1, key2) {
      const lines = text.trim().split('\n')
      const result = []
      for (let line of lines) {
        line = line.trim()
        if (!line) continue
        let parts = line.split(',').map(s => s.trim())
        if (parts.length < 2) parts = line.split('，').map(s => s.trim())
        if (parts.length >= 2 && parts[0] && parts[1]) {
          const obj = {}
          obj[key1] = parts[0]
          obj[key2] = parts[1]
          result.push(obj)
        }
      }
      return result
    },
    importStudents() {
      const students = this.parseLines(this.studentData, 'student_id', 'name')
      if (students.length === 0) {
        uni.showToast({ title: '请输入有效数据', icon: 'none' })
        return
      }
      uni.showLoading({ title: '导入中...' })
      this.request('import_students', { students: JSON.stringify(students) }, (data) => {
        uni.hideLoading()
        uni.showToast({ title: data.msg, icon: 'success' })
        this.showImportStudent = false
        this.studentData = ''
        this.fetchStudents()
        // 更新选课下拉列表
        if (this.currentTab === 'enrollment') this.fetchStudentsForSelect()
      })
    },

    // ---------- 教师 ----------
    fetchTeachers() {
      this.teacherLoading = true
      this.request('list_teachers', {}, (data) => {
        this.teacherList = data.data || []
        this.teacherOptions = this.teacherList.map(t => ({ id: t.id, name: t.name }))
        this.teacherLoading = false
      })
    },
    deleteTeacher(id) {
      uni.showModal({
        title: '确认删除',
        content: '删除教师将同时解除其课程关联，确定吗？',
        success: (res) => {
          if (res.confirm) {
            this.request('delete_teacher', { id }, () => {
              uni.showToast({ title: '删除成功', icon: 'success' })
              this.fetchTeachers()
            })
          }
        }
      })
    },
    importTeachers() {
      const teachers = this.parseLines(this.teacherData, 'teacher_id', 'name')
      if (teachers.length === 0) {
        uni.showToast({ title: '请输入有效数据', icon: 'none' })
        return
      }
      uni.showLoading({ title: '导入中...' })
      this.request('import_teachers', { teachers: JSON.stringify(teachers) }, (data) => {
        uni.hideLoading()
        uni.showToast({ title: data.msg, icon: 'success' })
        this.showImportTeacher = false
        this.teacherData = ''
        this.fetchTeachers()
      })
    },

    // ---------- 课程 ----------
    fetchCourses() {
      this.courseLoading = true
      this.request('list_courses', {}, (data) => {
        this.courseList = data.data || []
        this.courseLoading = false
      })
    },
    deleteCourse(id) {
      uni.showModal({
        title: '确认删除',
        content: '删除课程将同时删除关联的签到活动，确定吗？',
        success: (res) => {
          if (res.confirm) {
            this.request('delete_course', { id }, () => {
              uni.showToast({ title: '删除成功', icon: 'success' })
              this.fetchCourses()
            })
          }
        }
      })
    },
    editCourse(course) {
      this.editingCourse = course
      this.courseForm = { course_name: course.course_name, location: course.location || '', teacher_id: course.teacher_id }
      const teacher = this.teacherOptions.find(t => t.id === course.teacher_id)
      this.selectedTeacherName = teacher ? teacher.name : ''
      this.showCourseModal = true
    },
    onTeacherChange(e) {
      const index = e.detail.value
      const teacher = this.teacherOptions[index]
      this.courseForm.teacher_id = teacher.id
      this.selectedTeacherName = teacher.name
    },
    saveCourse() {
      if (!this.courseForm.course_name || !this.courseForm.teacher_id) {
        uni.showToast({ title: '请填写完整', icon: 'none' })
        return
      }
      const action = this.editingCourse ? 'update_course' : 'create_course'
      const data = { course_name: this.courseForm.course_name,
      location: this.courseForm.location,
      teacher_id: this.courseForm.teacher_id }
      if (this.editingCourse) data.id = this.editingCourse.id
      uni.showLoading({ title: '保存中...' })
      this.request(action, data, () => {
        uni.hideLoading()
        uni.showToast({ title: '保存成功', icon: 'success' })
        this.showCourseModal = false
        this.editingCourse = null
        this.courseForm = { course_name: '', location: '', teacher_id: '' }
        this.selectedTeacherName = ''
        this.fetchCourses()
        // 更新选课下拉列表
        if (this.currentTab === 'enrollment') this.fetchCoursesForSelect()
      })
    },

    // ---------- 选课管理 ----------
    async loadEnrollmentData() {
      await this.fetchStudentsForSelect()
      await this.fetchCoursesForSelect()
    },
    fetchStudentsForSelect() {
      this.request('list_students_for_select', {}, (data) => {
        this.studentOptions = data.data.map(s => ({ id: s.id, label: `${s.name} (${s.student_id})` }))
      })
    },
    fetchCoursesForSelect() {
      this.request('list_courses_for_select', {}, (data) => {
        this.courseOptionsForSelect = data.data
      })
    },
    onSelectStudent(e) {
      const idx = e.detail.value
      this.selectedStudentId = this.studentOptions[idx].id
      this.selectedStudentLabel = this.studentOptions[idx].label
    },
    onSelectCourse(e) {
      const idx = e.detail.value
      this.selectedCourseId = this.courseOptionsForSelect[idx].id
      this.selectedCourseName = this.courseOptionsForSelect[idx].course_name
      this.fetchEnrolledStudents()
    },
    fetchEnrolledStudents() {
      if (!this.selectedCourseId) return
      this.request('get_course_students', { course_id: this.selectedCourseId }, (data) => {
        this.enrolledStudents = data.data
      })
    },
    addStudentToCourse() {
      if (!this.selectedStudentId || !this.selectedCourseId) {
        uni.showToast({ title: '请选择学生和课程', icon: 'none' })
        return
      }
      this.request('add_student_to_course', { student_id: this.selectedStudentId, course_id: this.selectedCourseId }, () => {
        uni.showToast({ title: '添加成功', icon: 'success' })
        this.fetchEnrolledStudents()
        this.selectedStudentId = null
        this.selectedStudentLabel = ''
      })
    },
    // 批量添加学生到课程
    batchAddStudents() {
      if (!this.selectedCourseId) {
        uni.showToast({ title: '请先选择课程', icon: 'none' })
        return
      }
      const ids = this.batchStudentIds.trim().split('\n').map(s => s.trim()).filter(id => id)
      if (ids.length === 0) {
        uni.showToast({ title: '请输入学号', icon: 'none' })
        return
      }
      uni.showLoading({ title: '批量添加中...' })
      this.request('batch_add_students_to_course', { 
        student_ids: JSON.stringify(ids), 
        course_id: this.selectedCourseId 
      }, (data) => {
        uni.hideLoading()
        uni.showToast({ title: data.msg || '添加完成', icon: 'success' })
        this.batchStudentIds = ''
        this.fetchEnrolledStudents()
      })
    },
    removeStudentFromCourse(studentId) {
      this.request('remove_student_from_course', { student_id: studentId, course_id: this.selectedCourseId }, () => {
        uni.showToast({ title: '移除成功', icon: 'success' })
        this.fetchEnrolledStudents()
      })
    }
  }
}
</script>

<style scoped>
.container { min-height: 100vh; background: #f5f7fa; padding: 30rpx; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30rpx; }
.title { font-size: 48rpx; font-weight: bold; }
.logout-btn { background: #dc3545; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 28rpx; }
.tabs { display: flex; background: white; border-radius: 20rpx; margin-bottom: 30rpx; padding: 10rpx; flex-wrap: wrap; }
.tab { flex: 1; text-align: center; padding: 20rpx 0; border-radius: 16rpx; font-size: 26rpx; color: #6c757d; }
.tab.active { background: #007aff; color: white; }
.action-bar { margin-bottom: 20rpx; }
.import-btn { background: #007aff; color: white; padding: 20rpx; border-radius: 40rpx; font-size: 30rpx; }
.loading-state, .empty-state { text-align: center; padding: 100rpx 0; color: #868e96; }
.list { background: white; border-radius: 24rpx; overflow: hidden; }
.list-item { display: flex; justify-content: space-between; align-items: center; padding: 30rpx; border-bottom: 1rpx solid #f1f3f5; }
.item-info { flex: 1; }
.name { font-size: 32rpx; font-weight: bold; display: block; }
.id { font-size: 26rpx; color: #6c757d; }
.item-actions { display: flex; gap: 20rpx; }
.edit-btn { background: #ffc107; color: #212529; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 26rpx; }
.delete-btn { background: #dc3545; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 26rpx; }
.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; border-radius: 30rpx; padding: 40rpx; width: 650rpx; }
.modal-title { font-size: 36rpx; font-weight: bold; text-align: center; margin-bottom: 20rpx; }
.modal-tip { font-size: 26rpx; color: #6c757d; margin-bottom: 20rpx; text-align: center; }
.modal-textarea { background: #f5f5f5; border-radius: 20rpx; padding: 20rpx; font-size: 28rpx; width: 100%; height: 300rpx; margin-bottom: 30rpx; }
.modal-input { background: #f5f5f5; padding: 25rpx; border-radius: 16rpx; font-size: 30rpx; margin-bottom: 20rpx; width: 100%; }
.picker-display { background: #f5f5f5; padding: 25rpx; border-radius: 16rpx; font-size: 30rpx; margin-bottom: 20rpx; color: #333; }
.modal-buttons { display: flex; gap: 20rpx; }
.modal-btn { flex: 1; height: 80rpx; line-height: 80rpx; border-radius: 40rpx; font-size: 32rpx; border: none; }
.modal-btn.cancel { background: #f8f9fa; color: #6c757d; }
.modal-btn.confirm { background: #007aff; color: white; }

/* 选课管理样式 */
.enrollment-container { padding: 20rpx 0; }
.selector-row { margin-bottom: 30rpx; }
.picker-box { background: white; padding: 20rpx 30rpx; border-radius: 16rpx; text-align: center; }
.mode-switch { display: flex; background: white; border-radius: 40rpx; margin-bottom: 30rpx; padding: 10rpx; }
.mode-tab { flex: 1; text-align: center; padding: 20rpx; border-radius: 30rpx; font-size: 28rpx; color: #6c757d; }
.mode-tab.active { background: #007aff; color: white; }
.single-add { display: flex; gap: 20rpx; margin-bottom: 30rpx; }
.single-add .picker-box { flex: 1; }
.add-btn { background: #007aff; color: white; padding: 20rpx 40rpx; border-radius: 40rpx; }
.batch-add { margin-bottom: 30rpx; }
.batch-tip { font-size: 26rpx; color: #6c757d; margin-bottom: 15rpx; }
.batch-textarea { background: white; border-radius: 20rpx; padding: 20rpx; font-size: 28rpx; width: 100%; height: 200rpx; margin-bottom: 20rpx; }
.batch-btn { width: 100%; }
.enrolled-list { background: white; border-radius: 24rpx; padding: 20rpx; }
.list-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; margin-bottom: 20rpx; font-size: 30rpx; }
.refresh-btn { background: #e9ecef; color: #495057; padding: 10rpx 20rpx; border-radius: 20rpx; font-size: 24rpx; }
.empty-tip { color: #999; text-align: center; padding: 40rpx; }
.remove-btn { background: #dc3545; color: white; padding: 10rpx 30rpx; border-radius: 30rpx; font-size: 26rpx; }
</style>