<template>
  <div class="booking-app-scroll">
  <v-container class="booking-app-container pa-3 pa-sm-4">
    <v-card elevation="2" class="pa-4 booking-toolbar-card mb-4">
      <v-row class="booking-layout booking-toolbar-row" align="stretch">
        <v-col cols="6" lg="5" class="booking-filters-column">
          <v-row dense>
            <v-col cols="12">
              <v-autocomplete
                v-model="selectedDirection"
                :items="availableDirections"
                item-title="title"
                item-value="value"
                label="Выберите направление услуг"
                variant="outlined"
                @update:model-value="onDirectionChange"
                multiple
                chips
                clearable
                :disabled="loadingDoctors"
                :loading="loadingDoctors"
                :error-messages="directionErrorMessages"
                :error="directionErrorMessages.length > 0"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-stethoscope</v-icon>
                </template>
              </v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-autocomplete
                v-model="selectedService"
                :items="filteredServices"
                item-title="displayTitle"
                item-value="id"
                label="Выберите услугу"
                variant="outlined"
                @update:model-value="onServiceChange"
                :disabled="loadingServices"
                :loading="loadingServices"
                clearable
                :error-messages="serviceErrorMessages"
                :error="serviceErrorMessages.length > 0"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-medical-bag</v-icon>
                </template>
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ item.raw.title }}</v-list-item-title>
                    <v-list-item-subtitle>
                      {{ item.raw.priceText }} | {{ item.raw.durationText }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-autocomplete
                v-model="selectedClinic"
                :items="filteredClinics"
                item-title="title"
                item-value="id"
                label="Выберите филиал"
                variant="outlined"
                @update:model-value="onClinicChange"
                multiple
                chips
                clearable
                :disabled="loadingClinics"
                :loading="loadingClinics"
                :error-messages="clinicErrorMessages"
                :error="clinicErrorMessages.length > 0"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-hospital-building</v-icon>
                </template>
              </v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-autocomplete
                v-model="selectedDoctor"
                :items="filteredDoctors"
                item-title="title"
                item-value="ufCrm7Renovatioid"
                label="Выберите врача"
                variant="outlined"
                @update:model-value="onDoctorChange"
                :disabled="loadingDoctors"
                :loading="loadingDoctors"
                clearable
                :error-messages="doctorErrorMessages"
                :error="doctorErrorMessages.length > 0"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-doctor</v-icon>
                </template>
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template v-slot:prepend>
                      <v-avatar color="primary" size="36">
                        <v-icon size="18">mdi-doctor</v-icon>
                      </v-avatar>
                    </template>
                    <v-list-item-subtitle>{{ item.raw.ufCrm7Profession }}</v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>
          </v-row>
        </v-col>
        <v-col cols="6" lg="7" class="booking-calendar-column">
          <div class="calendar calendar-sidebar">
            <v-date-picker
              v-model="selectedDate"
              :min="minDate"
              :allowed-dates="hasAvailableSlotsForDate"
              locale="ru-RU"
              color="primary"
              header-color="primary"
              @update:model-value="onDateChange"
              @update:month="onMonthChange"
              @update:year="onYearChange"
              @click:month="handleMonthClick"
              @click:year="handleYearClick"
              ref="datePickerRef"
            ></v-date-picker>
            <!--
            <div v-if="!selectedDoctor" class="text-center py-4">
              <v-icon size="48" color="grey" class="mb-2">mdi-account-question</v-icon>
              <p class="text-grey select-text">Выберите врач для отображения расписания</p>
            </div>
            -->
          </div>
        </v-col>
            <div class="booking-schedule-block">
          <!-- Состояния загрузки и ошибки -->
          <v-row v-if="loadingSchedule || pending" class="mt-2">
            <v-col cols="12" class="text-center py-6">
              <v-progress-circular
                indeterminate
                color="primary"
                size="64"
              ></v-progress-circular>
              <p class="mt-4">Загрузка расписания...</p>
            </v-col>
          </v-row>

          <!-- Блок для отображения ошибок с деталями для пользователя -->
          <v-row v-if="error || scheduleError || apiError" class="mt-2">
            <v-col cols="12">
              <v-alert
                type="error"
                prominent
                closable
                @click:close="clearAllErrors"
                class="mb-0"
              >
                <v-alert-title>Ошибка загрузки данных</v-alert-title>
                <div class="mt-2">
                  <div v-if="error" class="mb-2">
                    <strong>Ошибка расписания:</strong> {{ error.message || JSON.stringify(error) }}
                  </div>

                  <div v-if="scheduleError" class="mb-2">
                    <strong>Ошибка загрузки расписания:</strong> {{ scheduleError }}
                  </div>

                  <div v-if="apiError" class="mb-2">
                    <strong>Ошибка API:</strong> {{ apiError }}
                  </div>

                  <div class="mt-3 text-caption" style="background: rgba(0,0,0,0.1); padding: 8px; border-radius: 4px;">
                    <details>
                      <summary>Техническая информация для поддержки (нажмите для раскрытия)</summary>
                      <pre class="mt-2" style="white-space: pre-wrap; font-size: 11px;">{{ debugInfo }}</pre>
                    </details>
                  </div>
                </div>

                <template v-slot:actions>
                  <v-btn @click="refresh" color="white" variant="text" class="mt-2">
                    <v-icon left>mdi-refresh</v-icon>
                    Попробовать снова
                  </v-btn>
                </template>
              </v-alert>
            </v-col>
          </v-row>

          <!-- Основной контент: таблица времени -->
          <v-card
            v-else-if="!error && !scheduleError && !apiError"
            elevation="2"
            class="booking-schedule-card mt-0"
          >
            <v-card-title class="text-subtitle-1 py-3 d-flex align-center flex-wrap">
              <v-icon class="mr-2">mdi-clock-outline</v-icon>
              <span>{{ selectedDateFormatted ? `Расписание на ${selectedDateFormatted}` : 'Выберите дату' }}</span>
            </v-card-title>
            <v-card-text class="booking-schedule-card__body pa-3 pt-0 flex-grow-1">
          
          <div v-if="!selectedDate" class="text-center py-8">
            <v-icon size="64" color="grey">mdi-calendar</v-icon>
            <p class="mt-4 text-grey">Выберите дату в календаре</p>
          </div>

          <div v-else-if="timeSlots.length === 0" class="text-center py-8">
            <v-icon size="64" color="grey">mdi-clock-off-outline</v-icon>
            <p class="mt-4 text-grey">На выбранную дату нет доступного времени</p>
          </div>

          <div v-else>
            <!-- Информация о враче -->
            <v-card class="mb-4" elevation="0" color="primary-lighten-5">
              <v-card-text class="pa-3">
                <div class="d-flex align-center">
                  <v-avatar class="mr-3" color="primary" size="48">
                    <v-icon size="24">mdi-doctor</v-icon>
                  </v-avatar>
                  <div>
                    <div class="text-h6 text-primary">{{ doctorInfo.name }}</div>
                    <div class="text-subtitle-2">{{ doctorInfo.ufCrm7Profession }}</div>
                    <div class="text-caption text-grey">{{ doctorInfo.cabinet }}</div>
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- Компактная инструкция -->
            <v-alert type="info" variant="tonal" class="mb-4" density="compact" border="start">
              <div class="d-flex align-center">
                <v-icon size="18" class="mr-2">mdi-gesture-tap</v-icon>
                <div>
                  <span class="font-weight-medium">Выберите начало и конец приема</span>
                  <span class="text-caption d-block text-grey">Кликните на первое и последнее время</span>
                </div>
              </div>
            </v-alert>

            <!-- Компактная легенда -->
            <div class="compact-legend mb-3">
              <div class="d-flex flex-wrap gap-2">
                <div class="legend-item" v-for="item in legendItems" :key="item.label">
                  <div class="legend-marker" :class="item.class"></div>
                  <span class="text-caption">{{ item.label }}</span>
                </div>
              </div>
            </div>

            <!-- Улучшенная таблица времени -->
            <div class="compact-time-table">
            <!-- Заголовок с часами -->
            <div class="time-header" :style="{ width: timeTableWidth }">
              <div 
                v-for="hourInfo in hourHeaders" 
                :key="hourInfo.hour"
                class="hour-header"
                :style="{ flex: `0 0 ${hourInfo.widthPercentage}%` }"
                :title="`${hourInfo.hour}:00 - ${hourInfo.hour + 1}:00`"
              >
                {{ hourInfo.hour }}:00
                <div v-if="hourInfo.cellCount < 12" class="text-caption text-grey">
                  ({{ hourInfo.cellCount * 5 }} мин)
                </div>
              </div>
            </div>


              <!-- Основная таблица -->
              <div class="time-grid-compact">
                <!-- Тонкая шкала с 5-минутными делениями -->
                <div class="minute-scale">
                  <div 
                    v-for="minute in minutesScale" 
                    :key="minute"
                    class="minute-mark"
                    :class="{ 'major-mark': minute % 15 === 0 }"
                  ></div>
                </div>

                <!-- 5-минутные ячейки -->
                <div 
                  v-for="slot in visibleTimeSlots" 
                  :key="slot.id"
                  :class="[
                    'time-cell',
                    slot.isBusy ? 'busy-cell' : 'free-cell',
                    !slot.isBusy && !isSlotSelectable(slot) ? 'limited-cell' : '',
                    slot.isStart ? 'start-cell' : '',
                    slot.isEnd ? 'end-cell' : '',
                    slot.isInRange ? 'selected-cell' : '',
                    slot.isHovered ? 'hover-cell' : ''
                  ]"
                  @click="selectSlot(slot)"
                  @mouseenter="hoverSlot(slot)"
                  @mouseleave="clearHover"
                  :title="getSlotTitle(slot)"
                >
                  <div class="slot-info" v-if="slot.isStart || slot.isEnd">
                    <div class="text-caption text-center">
                      {{ slot.room ? slot.room : '' }}
                    </div>
                  </div>
                  
                  <!-- Маркеры для начала/конца -->
                  <div v-if="slot.isStart" class="cell-marker start">
                    <v-icon size="10" color="white">mdi-play</v-icon>
                  </div>
                  <div v-if="slot.isEnd" class="cell-marker end">
                    <v-icon size="10" color="white">mdi-stop</v-icon>
                  </div>
                </div>
              </div>
            </div>

            <!-- Панель выбранного времени -->
            <v-card 
              v-if="selectedStartTime || selectedEndTime" 
              class="mt-4" 
              elevation="0"
              :color="selectedStartTime && selectedEndTime ? 'success-lighten-5' : 'warning-lighten-5'"
              border
            >
              <v-card-text class="pa-3">
                <div class="d-flex align-center justify-space-between selected-interval-content">
                  <div class="selected-interval-main">
                    <div class="d-flex align-center mb-1 selected-interval-title">
                      <v-icon 
                        size="20" 
                        :color="selectedStartTime && selectedEndTime ? 'success' : 'warning'"
                        class="mr-2"
                      >
                        {{ selectedStartTime && selectedEndTime ? 'mdi-check-circle' : 'mdi-clock-edit-outline' }}
                      </v-icon>
                      <span class="font-weight-medium">
                        {{ selectedStartTime && selectedEndTime ? 'Выбранный интервал' : 'Выберите конец приема' }}
                      </span>
                      <v-btn 
                        v-if="selectedStartTime && selectedEndTime"
                        variant="text"
                        size="x-small"
                        class="ml-2"
                        @click="isEditingTime = !isEditingTime"
                        :color="isEditingTime ? 'primary' : ''"
                      >
                        <v-icon size="16" class="mr-1">{{ isEditingTime ? 'mdi-close' : 'mdi-pencil' }}</v-icon>
                        {{ isEditingTime ? 'Отмена' : 'Изменить' }}
                      </v-btn>
                    </div>
                    <!-- Редактируемые поля времени -->
                    <div v-if="isEditingTime" class="d-flex align-center flex-wrap gap-3 mt-3">
                      
                      <v-text-field
                        v-model="editableStartTime"
                        label="Начало"
                        type="time"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 120px"
                        :rules="[timeValidation]"
                        :error-messages="timeEditError ? [timeEditError] : []"
                        :error="!!timeEditError"
                      ></v-text-field>
                      
                      <v-icon size="16" color="grey" class="selected-interval-arrow">mdi-arrow-right</v-icon>
                      
                      <v-text-field
                        v-model="editableEndTime"
                        label="Конец"
                        type="time"
                        variant="outlined"
                        density="compact"
                        hide-details
                        style="max-width: 120px"
                        :rules="[timeValidation]"
                        :error-messages="timeEditError ? [timeEditError] : []"
                        :error="!!timeEditError"
                      ></v-text-field>
                      
                      <v-btn 
                        color="primary"
                        size="small"
                        @click="applyTimeEdit"
                        :disabled="!isTimeEditValid"
                        class="ml-2"
                      >
                        <v-icon size="16" class="mr-1">mdi-check</v-icon>
                        Применить
                      </v-btn>
                    </div>
                    <div v-else class="d-flex align-center text-body-2 selected-interval-times">
                      <div class="time-display mr-4">
                        <span class="text-grey">Начало:</span>
                        <span class="ml-2 font-weight-medium">{{ selectedStartTime ? formatTime(selectedStartTime) : '--:--' }}</span>
                      </div>
                      <div class="time-display mx-4">
                        <span class="text-grey">Конец:</span>
                        <span class="ml-2 font-weight-medium">{{ selectedEndTime ? formatTime(selectedEndTime) : '--:--' }}</span>
                      </div>
                      <div class="time-display ml-4">
                        <span class="text-grey">Длительность:</span>
                        <span class="ml-2 font-weight-medium">
                          {{ durationInMinutes > 0 ? `${durationInMinutes} мин` : '--' }}
                        </span>
                      </div>
                      <div class="d-flex gap-2 confirm">
                    <v-btn 
                      v-if="selectedStartTime && selectedEndTime && selectedService && !isEditingTime"
                      color="success"
                      size="small"
                      @click="confirmBooking"
                      class="px-4"
                      :loading="bookingProgressActive"
                    >
                      <v-icon size="18" class="mr-1">mdi-check</v-icon>
                      Записаться
                    </v-btn>
                    
                    <v-btn 
                      variant="text"
                      size="small"
                      @click="clearSelection"
                      :disabled="(!selectedStartTime && !selectedEndTime) || bookingProgressActive"
                    >
                      <v-icon size="18">mdi-close</v-icon>
                    </v-btn>
                  </div>
                    </div>

                  </div>

                  <div
                    v-if="bookingProgressActive"
                    class="booking-progress-panel mt-3 pa-3 rounded"
                    role="status"
                    aria-live="polite"
                  >
                    <div class="booking-progress-text text-body-2 mb-2">
                      {{ bookingProgressMessage }}
                    </div>
                    <div class="booking-progress-track" aria-hidden="true">
                      <div
                        class="booking-progress-fill"
                        :style="{ width: `${bookingProgressValue}%` }"
                      ></div>
                      <div class="booking-progress-percent">
                        {{ bookingProgressValue }}%
                      </div>
                    </div>
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- Информация о процессе выбора -->
            <v-alert 
              v-if="selectedStartTime && !selectedEndTime" 
              type="warning" 
              variant="tonal" 
              class="mt-3" 
              density="compact"
              border="start"
            >
              <div class="d-flex align-center">
                <v-icon size="18" class="mr-2">mdi-cursor-default-click</v-icon>
                <span>Выбрано начало: <strong>{{ formatTime(selectedStartTime) }}</strong>. Кликните на время окончания приема</span>
              </div>
            </v-alert>

            <!-- Alert для ошибок при бронировании -->
            <v-alert
              v-if="bookingError"
              :type="bookingError.type || 'error'"
              variant="tonal"
              class="mt-3"
              closable
              @click:close="bookingError = null"
            >
              <template v-slot:title>
                {{ bookingError.title || 'Ошибка записи' }}
              </template>
              <div class="mt-2">
                {{ bookingError.message }}
              </div>
              <div v-if="bookingError.details" class="mt-2 text-caption" style="background: rgba(0,0,0,0.05); padding: 8px; border-radius: 4px;">
                <details>
                  <summary>Технические детали</summary>
                  <pre class="mt-2">{{ bookingError.details }}</pre>
                </details>
              </div>
            </v-alert>
          </div>
            </v-card-text>
          </v-card>
    </div>
      </v-row>
    </v-card>


  </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { callApi, callMethod, callMethodWithParams } from './callApi'

// Реактивные переменные
const selectedClinic = ref([])
const selectedDirection = ref([])
const selectedDoctor = ref(null)
const selectedService = ref(null)
const loadingClinics = ref(false)
const loadingDoctors = ref(false)
const loadingServices = ref(false)
const isEditingTime = ref(false)
const editableStartTime = ref('')
const editableEndTime = ref('')
const selectedDate = ref(null)
const timeSlots = ref([])
/** Интервалы, занятые успешной записью в этой сессии (до обновления данных с сервера). */
const clientBookedBlockedRanges = ref([])

const toDateKey = (dateVal) => {
  if (!dateVal) return ''
  const d = dateVal instanceof Date ? dateVal : new Date(dateVal)
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const applyClientBookedBusyToSlots = (slots, dateStr) => {
  const docId = String(selectedDoctor.value ?? '')
  const dateKey = toDateKey(dateStr)
  if (!docId || !dateKey || !slots.length) return

  for (const slot of slots) {
    for (const r of clientBookedBlockedRanges.value) {
      if (String(r.doctorId) !== docId || r.dateKey !== dateKey) continue
      if (slot.timestamp >= r.startTs && slot.timestamp < r.endTs) {
        slot.isBusy = true
        break
      }
    }
  }
}

const selectedStartTime = ref(null)
const selectedEndTime = ref(null)
const hoveredSlot = ref(null)
const currentMonth = ref(new Date().getMonth() + 1)
const currentYear = ref(new Date().getFullYear())
const datePickerRef = ref(null)

// Переменные для ошибок
const clinicsError = ref(null)
const doctorsError = ref(null)
const scheduleError = ref(null)
const apiError = ref(null)
const timeEditError = ref(null)
const bookingError = ref(null)
const servicesError = ref(null)

const bookingProgressActive = ref(false)
const bookingProgressMessage = ref('')
const bookingProgressValue = ref(0)

const REQUIRED_FILTER_MESSAGE = 'Обязательное поле'

const directionErrorMessages = computed(() => {
  return selectedDirection.value.length > 0 ? [] : [REQUIRED_FILTER_MESSAGE]
})

const serviceErrorMessages = computed(() => {
  return selectedService.value ? [] : [REQUIRED_FILTER_MESSAGE]
})

const clinicErrorMessages = computed(() => {
  const messages = []
  if (clinicsError.value) messages.push(clinicsError.value)
  if (selectedClinic.value.length === 0) messages.push(REQUIRED_FILTER_MESSAGE)
  return messages
})

const doctorErrorMessages = computed(() => {
  const messages = []
  if (doctorsError.value) messages.push(doctorsError.value)
  if (!selectedDoctor.value) messages.push(REQUIRED_FILTER_MESSAGE)
  return messages
})

const setBookingProgress = (message, value) => {
  bookingProgressMessage.value = message
  bookingProgressValue.value = Math.min(100, Math.max(0, value))
  bookingProgressActive.value = true
}

const resetBookingProgress = () => {
  bookingProgressActive.value = false
  bookingProgressMessage.value = ''
  bookingProgressValue.value = 0
}

// Получаем ID клиники в системе Renovatio
const selectedClinicRenovatioIds = computed(() => {
  if (!Array.isArray(selectedClinic.value) || selectedClinic.value.length === 0) return []
  const selectedClinicIds = selectedClinic.value.map(id => String(id))

  return clinics.value
    .filter(c => selectedClinicIds.includes(String(c.id)))
    .map(c => c.ufCrm9Renovatioid)
    .filter(Boolean)
})

// Watchers для редактирования времени
watch(selectedStartTime, (newVal) => {
  if (newVal) {
    editableStartTime.value = newVal
  }
})

watch(selectedEndTime, (newVal) => {
  if (newVal) {
    editableEndTime.value = newVal
  }
})

// Автозагрузка расписания, когда врач уже выбран, а клиника(и) выбираются позже.
watch(
  [selectedDoctor, selectedClinicRenovatioIds],
  ([doctorId, clinicIds]) => {
    if (doctorId && Array.isArray(clinicIds) && clinicIds.length > 0) {
      loadScheduleForMonth(currentMonth.value, currentYear.value)
    }
  }
)

// Функция для очистки всех ошибок
const clearAllErrors = () => {
  clinicsError.value = null
  doctorsError.value = null
  servicesError.value = null
  scheduleError.value = null
  apiError.value = null
  timeEditError.value = null
  bookingError.value = null
}

// Вычисляемое свойство для отладочной информации
const debugInfo = computed(() => {
  return {
    selectedClinic: selectedClinic.value,
    selectedDoctor: selectedDoctor.value,
    selectedClinicRenovatioIds: selectedClinicRenovatioIds.value,
    currentMonth: currentMonth.value,
    currentYear: currentYear.value,
    selectedDate: selectedDate.value,
    timeSlotsCount: timeSlots.value.length,
    hasData: !!data.value,
    url: url.value,
    clinicsCount: clinics.value.length,
    doctorsCount: allDoctors.value.length,
    filteredDoctorsCount: filteredDoctors.value.length,
    timestamp: new Date().toISOString()
  }
})

const syncCalendarViewAndLoad = (month, year) => {
  if (!Number.isFinite(month) || !Number.isFinite(year)) return
  currentMonth.value = Number(month)
  currentYear.value = Number(year)

  if (selectedDoctor.value) {
    loadScheduleForMonth(currentMonth.value, currentYear.value)
  }
}

const handleMonthClick = () => {
  // Даем время для обновления внутреннего состояния date-picker
  setTimeout(() => {
    if (!datePickerRef.value) return
    const currentView = datePickerRef.value.displayedDate
    if (!currentView) return

    const month = currentView.getMonth() + 1 // Преобразуем в 1-based
    const year = currentView.getFullYear()
    syncCalendarViewAndLoad(month, year)
  }, 50)
}

// Обработчик клика по году
const handleYearClick = () => {
  // Используем тот же таймаут для синхронизации
  setTimeout(() => {
    if (!datePickerRef.value) return
    const currentView = datePickerRef.value.displayedDate
    if (!currentView) return

    const month = currentView.getMonth() + 1
    const year = currentView.getFullYear()
    syncCalendarViewAndLoad(month, year)
  }, 50)
}

// Метод применения изменений времени
const applyTimeEdit = () => {
  timeEditError.value = null
  
  if (!isTimeEditValid.value) {
    timeEditError.value = 'Указано некорректное время'
    return
  }
  
  // Находим соответствующие слоты
  const startSlot = timeSlots.value.find(s => s.time === editableStartTime.value)
  const endSlot = timeSlots.value.find(s => s.time === editableEndTime.value)
  
  if (!startSlot || !endSlot) {
    timeEditError.value = 'Указанное время не найдено в расписании'
    return
  }
  
  // Проверяем, что слоты свободны
  if (startSlot.isBusy || endSlot.isBusy) {
    timeEditError.value = 'Выбранное время занято'
    return
  }
  
  const startIndex = timeSlots.value.findIndex(s => s.time === editableStartTime.value)
  const endIndex = timeSlots.value.findIndex(s => s.time === editableEndTime.value)
  
  // Проверяем порядок времени
  if (endIndex <= startIndex) {
    timeEditError.value = 'Время окончания должно быть позже времени начала'
    return
  }
  
  // Проверяем, что все слоты диапазона существуют и свободны
  let allSlotsFree = true
  for (let i = startIndex; i <= endIndex; i++) {
    if (!timeSlots.value[i] || timeSlots.value[i].isBusy) {
      allSlotsFree = false
      break
    }
  }
  
  if (!allSlotsFree) {
    timeEditError.value = 'Не все выбранные интервалы свободны'
    return
  }

  if (selectedServiceDurationMinutes.value > 0) {
    const spanMinutes = (endIndex - startIndex) * 5
    if (spanMinutes < selectedServiceDurationMinutes.value) {
      timeEditError.value = `Длительность приёма не меньше ${selectedServiceDurationMinutes.value} мин (выбрано ${spanMinutes} мин)`
      return
    }
  }
  
  // Применяем изменения
  clearSlotSelection()
  
  selectedStartTime.value = editableStartTime.value
  selectedEndTime.value = editableEndTime.value
  
  startSlot.isStart = true
  endSlot.isEnd = true
  
  // Выделяем промежуточные слоты
  for (let i = startIndex + 1; i < endIndex; i++) {
    timeSlots.value[i].isInRange = true
  }
  
  isEditingTime.value = false
}

// Валидация времени
const timeValidation = (value) => {
  if (!value) return true
  
  // Проверяем формат HH:MM
  const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/
  if (!timeRegex.test(value)) {
    return 'Введите время в формате ЧЧ:ММ'
  }
  
  // Проверяем, что время есть в слотах
  const slotExists = timeSlots.value.some(slot => slot.time === value)
  if (!slotExists) {
    return 'Время не найдено в расписании'
  }
  
  return true
}

// Проверка валидности редактирования
const isTimeEditValid = computed(() => {
  if (!editableStartTime.value || !editableEndTime.value) return false
  
  const startSlot = timeSlots.value.find(s => s.time === editableStartTime.value)
  const endSlot = timeSlots.value.find(s => s.time === editableEndTime.value)
  
  if (!startSlot || !endSlot || startSlot.isBusy || endSlot.isBusy) {
    return false
  }
  
  const startIndex = timeSlots.value.findIndex(s => s.time === editableStartTime.value)
  const endIndex = timeSlots.value.findIndex(s => s.time === editableEndTime.value)
  
  if (endIndex <= startIndex) return false

  for (let i = startIndex; i <= endIndex; i++) {
    if (!timeSlots.value[i] || timeSlots.value[i].isBusy) return false
  }

  if (selectedServiceDurationMinutes.value > 0) {
    const spanMinutes = (endIndex - startIndex) * 5
    if (spanMinutes < selectedServiceDurationMinutes.value) return false
  }

  return true
})

// Очистка выбора
const clearSelection = () => {
  selectedStartTime.value = null
  selectedEndTime.value = null
  isEditingTime.value = false
  timeEditError.value = null
  clearSlotSelection()
}

// При изменении даты
const onDateChange = (date) => {
  selectedDate.value = date
  
  // Если меняется дата (не месяц/год), обновляем слоты
  if (date && data.value) {
    try {
      timeSlots.value = parseScheduleToTimeSlots(date)
    } catch (err) {
      console.error('Ошибка при парсинге расписания:', err)
      scheduleError.value = `Ошибка при обработке расписания: ${err.message}`
    }
  }
  
  clearSelection()
  isEditingTime.value = false
  
  // Если есть выбранный врач и мы переключились на другую дату в том же месяце,
  // нужно убедиться, что расписание загружено
  if (selectedDoctor.value && date) {
    const dateObj = new Date(date)
    const month = dateObj.getMonth() + 1
    const year = dateObj.getFullYear()
    
    // Проверяем, нужно ли загружать расписание для этого месяца
    if (month !== currentMonth.value || year !== currentYear.value) {
      currentMonth.value = month
      currentYear.value = year
      loadScheduleForMonth(month, year)
    }
  }
}

// При изменении года в календаре
const onYearChange = (year) => {
  const normalizedYear = Number(year)
  if (!Number.isFinite(normalizedYear)) return
  syncCalendarViewAndLoad(currentMonth.value, normalizedYear)
}

// При изменении месяца в календаре
const onMonthChange = (month) => {
  // Месяц в v-date-picker передается как 0-based (0-11)
  // Преобразуем в 1-based (1-12)
  const normalizedMonth = Number(month)
  if (!Number.isFinite(normalizedMonth)) return
  syncCalendarViewAndLoad(normalizedMonth + 1, currentYear.value)
}
let loadScheduleTimeout = null
// Загрузка расписания для указанного месяца
const loadScheduleForMonth = async (month, year) => {
  if (!selectedDoctor.value || selectedClinicRenovatioIds.value.length === 0) return
  
  // Сбрасываем ошибку расписания перед новой загрузкой
  scheduleError.value = null
  loadingSchedule.value = true
  
  // Отменяем предыдущий таймаут, если он есть
  if (loadScheduleTimeout) {
    clearTimeout(loadScheduleTimeout)
  }
  
  // Используем debounce для предотвращения множественных вызовов
  loadScheduleTimeout = setTimeout(async () => {
    // Пользователь мог очистить фильтры во время debounce.
    // В этом случае не отправляем запрос с пустыми параметрами.
    if (!selectedDoctor.value || selectedClinicRenovatioIds.value.length === 0) {
      loadingSchedule.value = false
      pending.value = false
      loadScheduleTimeout = null
      return
    }

    pending.value = true
    error.value = null
    
    try {
      // Формируем даты для выбранного месяца
      const startDate = new Date(year, month - 1, 1)
      const endDate = new Date(year, month, 1) // Последний день месяца
      
      const startDateStr = startDate.toISOString().replace('T', ' ').slice(0, 16)
      const endDateStr = endDate.toISOString().replace('T', ' ').slice(0, 16)
      
      const aggregatedResult = {
        schedule: [],
        freeSlots: {}
      }

      for (const clinicRenovatioId of selectedClinicRenovatioIds.value) {
        const url = `https://renovoapp.webtm.ru/index.php?action=get_calendar&doctor_id=${selectedDoctor.value}&clinic_id=${clinicRenovatioId}&time_start=${startDateStr}&time_end=${endDateStr}`
        console.log('Загрузка расписания для месяца:', { month, year, clinicRenovatioId, url })

        const response = await fetch(url, {
          method: 'GET',
          mode: 'cors',
          headers: {
            'Accept': 'application/json',
          }
        })

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`)
        }

        const result = await response.json()
        console.log('Получены данные расписания:', result)

        if (!result || typeof result !== 'object') {
          throw new Error('Неверный формат ответа от сервера')
        }

        if (Array.isArray(result.schedule) && result.schedule.length > 0) {
          aggregatedResult.schedule.push(...result.schedule)
        }

        if (result.freeSlots && typeof result.freeSlots === 'object') {
          for (const [doctorKey, slots] of Object.entries(result.freeSlots)) {
            const existing = Array.isArray(aggregatedResult.freeSlots[doctorKey]) ? aggregatedResult.freeSlots[doctorKey] : []
            aggregatedResult.freeSlots[doctorKey] = [...existing, ...(Array.isArray(slots) ? slots : [])]
          }
        }
      }
      
      // ВАЖНО: Очищаем старые данные для этого месяца
      if (data.value) {
        // Удаляем старые записи для загружаемого месяца
        data.value.schedule = data.value.schedule.filter(item => {
          const itemDate = parseDateString(item.date)
          return !itemDate || 
                 itemDate.getMonth() + 1 !== month || 
                 itemDate.getFullYear() !== year
        })
        
        // Добавляем новые данные
        if (aggregatedResult.schedule.length > 0) {
          data.value.schedule = [...data.value.schedule, ...aggregatedResult.schedule]
        }
      } else {
        // Инициализируем данные
        data.value = {
          schedule: aggregatedResult.schedule || [],
          freeSlots: aggregatedResult.freeSlots || {},
        }
      }
      
      // Обновляем freeSlots
      if (aggregatedResult.freeSlots) {
        if (!data.value.freeSlots) {
          data.value.freeSlots = {}
        }
        
        for (const [doctorId, slots] of Object.entries(aggregatedResult.freeSlots)) {
          data.value.freeSlots[doctorId] = slots
        }
      }

      // Если есть выбранная дата в текущем месяце, обновляем слоты
      if (selectedDate.value) {
        const selectedDateObj = new Date(selectedDate.value)
        if (selectedDateObj.getMonth() + 1 === month && selectedDateObj.getFullYear() === year) {
          try {
            timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
          } catch (parseErr) {
            console.error('Ошибка при парсинге слотов:', parseErr)
            scheduleError.value = `Ошибка при обработке слотов времени: ${parseErr.message}`
          }
        }
      }
      
    } catch (err) {
      console.error('Ошибка загрузки расписания для месяца:', err)
      scheduleError.value = `Не удалось загрузить расписание для выбранного месяца: ${err.message}`
      error.value = {
        message: scheduleError.value
      }
    } finally {
      loadingSchedule.value = false
      pending.value = false
      loadScheduleTimeout = null
    }
  }, 100) // Задержка 100ms для предотвращения слишком частых запросов
}
// Получаем информацию о текущем враче
const currentDoctorInfo = computed(() => {
  if (!selectedDoctor.value) return null
  
  const doctor = allDoctors.value.find(d => d.ufCrm7Renovatioid === selectedDoctor.value)

  if (doctor) {
    return {
      id: doctor.ufCrm7Renovatioid,
      bxId: doctor.id,
      name: doctor.name,
      ufCrm7Profession: doctor.ufCrm7Profession,
      cabinet: doctor.cabinet
    }
  } else {
    return null
  } 
})

// При изменении выбора врача
const onDoctorChange = (doctorId) => {
  selectedDoctor.value = doctorId
  scheduleError.value = null

  if (doctorId) {
    const doctor = allDoctors.value.find(d => String(d.ufCrm7Renovatioid) === String(doctorId))
    const doctorDirections = doctor ? normalizeDoctorDirections(doctor.ufCrm7Profession) : []

    if (doctorDirections.length === 1) {
      selectedDirection.value = [doctorDirections[0]]
    }

    if (selectedService.value) {
      const availability = serviceAvailability.value[selectedService.value]
      if (availability && !availability.doctorIds.includes(String(doctorId))) {
        selectedService.value = null
      }
    }

    // Загружаем расписание для текущего месяца
    loadScheduleForMonth(currentMonth.value, currentYear.value)
  } else {
    data.value = null
    timeSlots.value = []
  }
}

// Обновленный обработчик изменения направления
const onDirectionChange = () => {
  if (selectedService.value && !filteredServices.value.some(s => s.id === selectedService.value)) {
    selectedService.value = null
  }

  if (selectedClinic.value.length > 0) {
    selectedClinic.value = selectedClinic.value.filter(clinicId =>
      filteredClinics.value.some(c => String(c.id) === String(clinicId))
    )
  }
}

// Обновленный обработчик изменения клиники
const onClinicChange = (clinicIds) => {
  const newClinics = Array.isArray(clinicIds) ? clinicIds : (clinicIds ? [clinicIds] : [])
  selectedClinic.value = newClinics

  if (selectedService.value && !filteredServices.value.some(s => s.id === selectedService.value)) {
    selectedService.value = null
  }
}

// Обновленный обработчик изменения услуги
const onServiceChange = () => {
  const service = services.value.find(item => item.id === selectedService.value)
  console.log('Услуга выбрана:', {
    serviceId: selectedService.value,
    serviceTitle: service?.title,
    parsedDurationMinutes: service?.durationMinutes ?? 0
  })

  if (!selectedService.value) return

  const availability = serviceAvailability.value[selectedService.value]
  if (!availability) return

  if (selectedClinic.value.length > 0) {
    selectedClinic.value = selectedClinic.value.filter(clinicId =>
      availability.clinicIds.includes(String(clinicId))
    )
  }
}

const url = computed(() => {
  if (!selectedDoctor.value || selectedClinicRenovatioIds.value.length === 0) return null
  
  // Используем текущий месяц для начальной загрузки
  const startDate = new Date(currentYear.value, currentMonth.value - 1, 1)
  const endDate = new Date(currentYear.value, currentMonth.value, 1)
  console.log('URL computed:', { year: currentYear.value, month: currentMonth.value })
  
  const startDateStr = startDate.toISOString().replace('T', ' ').slice(0, 16)
  const endDateStr = endDate.toISOString().replace('T', ' ').slice(0, 16)

  return `https://renovoapp.webtm.ru/index.php?action=get_calendar&doctor_id=${selectedDoctor.value}&clinic_id=${selectedClinicRenovatioIds.value[0]}&time_start=${startDateStr}&time_end=${endDateStr}`
})

const services = ref([])

const parseServiceDurationMinutes = (service) => {
  const rawDuration = service.durationMinutes ?? service.duration ?? service.time ?? service.duration_minutes ?? service.length ?? service.service_duration
  if (rawDuration == null) return 0

  if (typeof rawDuration === 'number') return rawDuration

  const durationText = String(rawDuration).trim()
  if (!durationText) return 0

  const hhmmMatch = durationText.match(/^(\d{1,2}):(\d{2})$/)
  if (hhmmMatch) {
    const hours = Number(hhmmMatch[1])
    const minutes = Number(hhmmMatch[2])
    return hours * 60 + minutes
  }

  const hhmmssMatch = durationText.match(/^(\d{1,2}):(\d{2}):(\d{2})$/)
  if (hhmmssMatch) {
    const hours = Number(hhmmssMatch[1])
    const minutes = Number(hhmmssMatch[2])
    const seconds = Number(hhmmssMatch[3])
    return hours * 60 + minutes + (seconds > 0 ? 1 : 0)
  }

  const isoMinutesMatch = durationText.match(/PT(?:(\d+)H)?(?:(\d+)M)?/i)
  if (isoMinutesMatch) {
    const hours = Number(isoMinutesMatch[1] || 0)
    const minutes = Number(isoMinutesMatch[2] || 0)
    const totalMinutes = hours * 60 + minutes
    if (totalMinutes > 0) return totalMinutes
  }

  const numberMatch = durationText.match(/\d+/)
  return numberMatch ? Number(numberMatch[0]) : 0
}

const selectedServiceDurationMinutes = computed(() => {
  if (!selectedService.value) return 0
  const service = services.value.find(item => item.id === selectedService.value)
  if (!service) return 0
  if (typeof service.durationMinutes === 'number' && service.durationMinutes > 0) {
    return service.durationMinutes
  }
  return parseServiceDurationMinutes(service)
})

const { data, pending, error, refresh } = await useFetch(
  () => url.value,
  {
    immediate: false // Не загружать сразу
  }
)

const setTodayDate = () => {
  const today = new Date()
  selectedDate.value = today.toISOString().split('T')[0] // Формат YYYY-MM-DD
}

// Моковые данные для клиник и врачей
const clinics = ref([])
const allDoctors = ref([])

const availableDirections = computed(() => {
  const directionSet = new Set()
  const selectedClinicIds = selectedClinic.value.map(id => String(id))
  const selectedServiceData = selectedService.value ? serviceAvailability.value[selectedService.value] : null
  const serviceDoctorLimit = selectedServiceData ? new Set(selectedServiceData.doctorIds || []) : null
  const selectedDoctorId = selectedDoctor.value ? String(selectedDoctor.value) : null

  allDoctors.value.forEach(doctor => {
    const doctorId = String(doctor.ufCrm7Renovatioid || '')
    if (!doctorId) return

    if (selectedDoctorId && doctorId !== selectedDoctorId) return
    if (serviceDoctorLimit && !serviceDoctorLimit.has(doctorId)) return

    const doctorClinics = doctorClinicIds(doctor)
    if (selectedClinicIds.length > 0 && !selectedClinicIds.some(clinicId => doctorClinics.includes(clinicId))) {
      return
    }

    const directions = normalizeDoctorDirections(doctor.ufCrm7Profession)
    directions.forEach(direction => directionSet.add(direction))
  })

  return Array.from(directionSet)
    .sort((a, b) => a.localeCompare(b, 'ru'))
    .map(direction => ({
      title: direction,
      value: direction
    }))
})

const normalizeDoctorDirections = (professionValue) => {
  if (!professionValue) return []

  const rawDirections = Array.isArray(professionValue)
    ? professionValue
    : String(professionValue).split(',')

  return rawDirections
    .map(item => String(item).trim())
    .filter(Boolean)
}

const doctorClinicIds = (doctor) => {
  return Array.isArray(doctor.ufCrm7Clinics)
    ? doctor.ufCrm7Clinics.map(item => String(item))
    : [String(doctor.ufCrm7Clinics)]
}

const serviceAvailability = ref({})
const serviceRequestCache = ref({})

const normalizeService = (rawService) => {
  const id = rawService.service_id
  const title = rawService.title ?? rawService.sub_code
  const durationMinutes = parseServiceDurationMinutes(rawService)
  const rawPrice = rawService.price
  const priceText = rawPrice != null && rawPrice !== '' ? `${rawPrice}` : 'Цена не указана'
  const durationText = durationMinutes > 0 ? `${durationMinutes} мин` : (rawService.duration ?? rawService.time ?? 'Длительность не указана')

  return {
    id: String(id),
    title: String(title),
    price: rawPrice,
    durationMinutes,
    durationText: String(durationText),
    priceText: String(priceText),
    displayTitle: `${title} (${priceText}, ${durationText})`,
    raw: rawService
  }
}

const getDoctorsBySelections = ({
  ignoreClinic = false,
  ignoreDirection = false,
  ignoreService = false,
  ignoreDoctor = false
} = {}) => {
  const selectedClinicIds = ignoreClinic ? [] : selectedClinic.value.map(id => String(id))
  const selectedDirections = ignoreDirection ? [] : selectedDirection.value
  const selectedServiceData = (!ignoreService && selectedService.value) ? serviceAvailability.value[selectedService.value] : null
  const serviceDoctorLimit = selectedServiceData ? new Set(selectedServiceData.doctorIds || []) : null
  const selectedDoctorId = (!ignoreDoctor && selectedDoctor.value) ? String(selectedDoctor.value) : null

  return allDoctors.value.filter(doctor => {
    const doctorId = String(doctor.ufCrm7Renovatioid || '')
    if (!doctorId) return false

    if (selectedDoctorId && doctorId !== selectedDoctorId) return false
    if (serviceDoctorLimit && !serviceDoctorLimit.has(doctorId)) return false

    const doctorDirections = normalizeDoctorDirections(doctor.ufCrm7Profession)
    if (selectedDirections.length > 0 && !selectedDirections.some(direction => doctorDirections.includes(direction))) {
      return false
    }

    const doctorClinics = doctorClinicIds(doctor)
    if (selectedClinicIds.length > 0 && !selectedClinicIds.some(clinicId => doctorClinics.includes(clinicId))) {
      return false
    }

    return true
  })
}

const doctorsFilteredBySelections = computed(() => getDoctorsBySelections())

const filteredDoctors = computed(() => getDoctorsBySelections({ ignoreDoctor: true }))

const filteredClinics = computed(() => {
  const allowedClinicIds = new Set()
  const doctorsByOtherFields = getDoctorsBySelections({ ignoreClinic: true })

  doctorsByOtherFields.forEach(doctor => {
    doctorClinicIds(doctor).forEach(clinicId => {
      allowedClinicIds.add(clinicId)
    })
  })

  return clinics.value.filter(clinic => allowedClinicIds.has(String(clinic.id)))
})

const filteredServices = computed(() => {
  const selectedClinicIds = selectedClinic.value.map(id => String(id))
  const selectedDirections = selectedDirection.value
  const doctorId = selectedDoctor.value ? String(selectedDoctor.value) : null
  const doctorsByOtherFields = getDoctorsBySelections({ ignoreService: true })
  const allowedDoctorIds = new Set(doctorsByOtherFields.map(doctor => String(doctor.ufCrm7Renovatioid)))

  return services.value.filter(service => {
    const availability = serviceAvailability.value[service.id]
    if (!availability) return false

    if (doctorId && !availability.doctorIds.includes(doctorId)) return false
    if (allowedDoctorIds.size > 0 && !availability.doctorIds.some(docId => allowedDoctorIds.has(String(docId)))) return false

    if (selectedClinicIds.length > 0 && !selectedClinicIds.some(clinicId => availability.clinicIds.includes(clinicId))) {
      return false
    }

    if (!doctorId && selectedDirections.length > 0) {
      const hasDirection = availability.doctorIds.some(docId => {
        const doctor = allDoctors.value.find(d => String(d.ufCrm7Renovatioid) === String(docId))
        if (!doctor) return false
        const directions = normalizeDoctorDirections(doctor.ufCrm7Profession)
        return selectedDirections.some(direction => directions.includes(direction))
      })
      if (!hasDirection) return false
    }

    return true
  })
})

const loadServicesIndex = async () => {
  servicesError.value = null
  services.value = []
  serviceAvailability.value = {}

  loadingServices.value = true
  try {
    const loadedServices = []
    const availabilityMap = {}

    for (const doctor of allDoctors.value) {
      const doctorRenovatioId = String(doctor.ufCrm7Renovatioid || '')
      if (!doctorRenovatioId) continue

      const doctorClinics = doctorClinicIds(doctor)
      for (const clinicId of doctorClinics) {
        const clinic = clinics.value.find(c => String(c.id) === clinicId)
        const clinicRenovatioId = clinic?.ufCrm9Renovatioid
        if (!clinicRenovatioId) continue

        const cacheKey = `${doctorRenovatioId}_${clinicRenovatioId}`
        let serviceList = serviceRequestCache.value[cacheKey]

        if (!serviceList) {
          const url = `https://renovoapp.webtm.ru/index.php?action=get_services&doctor_id=${doctorRenovatioId}&clinic_id=${clinicRenovatioId}`
          const response = await fetch(url, {
            method: 'GET',
            mode: 'cors',
            headers: {
              'Accept': 'application/json',
            }
          })

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status} ${response.statusText}`)
          }

          const result = await response.json()
          serviceList = Array.isArray(result) ? result : (result.services || result.items || [])
          serviceRequestCache.value[cacheKey] = serviceList
        }

        serviceList.forEach(service => {
          loadedServices.push(service)
          const normalized = normalizeService(service)
          if (!availabilityMap[normalized.id]) {
            availabilityMap[normalized.id] = {
              doctorIds: new Set(),
              clinicIds: new Set()
            }
          }
          availabilityMap[normalized.id].doctorIds.add(doctorRenovatioId)
          availabilityMap[normalized.id].clinicIds.add(String(clinic.id))
        })
      }
    }

    const uniqueMap = new Map()
    loadedServices.forEach(service => {
      const normalized = normalizeService(service)
      if (!uniqueMap.has(normalized.id)) {
        uniqueMap.set(normalized.id, normalized)
      }
    })

    services.value = Array.from(uniqueMap.values())
    serviceAvailability.value = Object.fromEntries(
      Object.entries(availabilityMap).map(([serviceId, value]) => ([
        serviceId,
        {
          doctorIds: Array.from(value.doctorIds),
          clinicIds: Array.from(value.clinicIds)
        }
      ]))
    )
  } catch (err) {
    console.error('Ошибка загрузки услуг:', err)
    servicesError.value = err.message || 'Не удалось загрузить список услуг'
  } finally {
    loadingServices.value = false
  }
}

// вызов API для получения клиник
const fetchClinics = async () => {
  loadingClinics.value = true
  clinicsError.value = null
  
  try {
    const response = await callApi('crm.item.list', {}, ["id", "title", "ufCrm9Renovatioid"], 1044)
    await fetchDoctors()

    // Фильтруем клиники: оставляем только те, у которых есть врачи
    clinics.value = response.filter(clinic => {
      const hasDoctors = allDoctors.value.some(doctor => 
        doctor.ufCrm7Clinics == clinic.id
      )
      return hasDoctors
    })
    
    console.log('Загружено клиник:', clinics.value.length)
  } catch (error) {
    console.error('Ошибка загрузки клиник:', error)
    clinicsError.value = error.message || 'Не удалось загрузить список клиник'
  } finally {
    loadingClinics.value = false
  }
}

// Моковый вызов API для получения врачей
const fetchDoctors = async () => {
  loadingDoctors.value = true
  doctorsError.value = null
  
  try {
    const response = await callApi('crm.item.list', {}, ["id", "title", "ufCrm7Clinics", 'ufCrm7Profession', 'ufCrm7Renovatioid'], 1040)
    allDoctors.value = response
    console.log('Загружено врачей:', allDoctors.value.length)
  } catch (error) {
    console.error('Ошибка загрузки врачей:', error)
    doctorsError.value = error.message || 'Не удалось загрузить список врачей'
  } finally {
    loadingDoctors.value = false
  }
}

// Информация о враче
const doctorInfo = computed(() => {
  if (!selectedDoctor.value || !data.value) {
    return {
      name: 'Не выбран врач',
      ufCrm7Profession: '—',
      cabinet: '—'
    }
  }
  
  // Находим врача по ID
  const doctor = allDoctors.value.find(d => d.ufCrm7Renovatioid == selectedDoctor.value)

  if (doctor) {
    return {
      name: doctor.title || doctor.name || 'Неизвестно',
      ufCrm7Profession: doctor.ufCrm7Profession || 'Специализация не указана',
    }
  }
  
  return {
    name: 'Врач не найден',
    ufCrm7Profession: '—',
    cabinet: '—'
  }
})

// Диапазон дат для календаря
const minDate = ref(new Date().toISOString().split('T')[0])

const normalizeDateToKey = (dateInput) => {
  if (!dateInput) return null

  const date = dateInput instanceof Date ? dateInput : new Date(dateInput)
  if (Number.isNaN(date.getTime())) return null

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const doctorFreeSlots = computed(() => {
  if (!data.value?.freeSlots) return []

  const freeSlotsByDoctor = data.value.freeSlots
  const possibleDoctorIds = [
    selectedDoctor.value?.toString(),
    doctorId.value?.toString()
  ].filter(Boolean)

  for (const id of possibleDoctorIds) {
    if (Array.isArray(freeSlotsByDoctor[id])) {
      return freeSlotsByDoctor[id]
    }
  }

  return []
})

const availableDateKeys = computed(() => {
  const result = new Set()

  doctorFreeSlots.value.forEach(slot => {
    if (slot?._date) {
      const normalized = normalizeDateToKey(slot._date)
      if (normalized) result.add(normalized)
      return
    }

    if (slot?.date) {
      const parsed = parseDateString(slot.date)
      const normalized = normalizeDateToKey(parsed)
      if (normalized) result.add(normalized)
    }
  })

  return result
})

const hasAvailableSlotsForDate = (date) => {
  if (!selectedDoctor.value) return true

  const normalizedDate = normalizeDateToKey(date)
  if (!normalizedDate) return false

  if (normalizedDate < minDate.value) return false

  return availableDateKeys.value.has(normalizedDate)
}

// Форматирование выбранной даты
const selectedDateFormatted = computed(() => {
  if (!selectedDate.value) return ''
  const date = new Date(selectedDate.value)
  return date.toLocaleDateString('ru-RU', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

const isScheduleForDate = (scheduleItem, dateStr) => {
  const itemDate = parseDateString(scheduleItem.date)
  const selected = new Date(dateStr)
  return itemDate && selected &&
         itemDate.getDate() === selected.getDate() &&
         itemDate.getMonth() === selected.getMonth() &&
         itemDate.getFullYear() === selected.getFullYear()
}

const isScheduleForSelectedDoctor = (scheduleItem) => {
  if (!selectedDoctor.value) return true
  return String(scheduleItem.user_id ?? '') === String(selectedDoctor.value)
}

const isScheduleForSelectedClinic = (scheduleItem) => {
  if (selectedClinicRenovatioIds.value.length === 0) return true
  return selectedClinicRenovatioIds.value
    .map(id => String(id))
    .includes(String(scheduleItem.clinic_id ?? ''))
}

// Функция для парсинга графика из data.schedule
const parseScheduleToTimeSlots = (dateStr) => {
  if (!data.value?.schedule || !Array.isArray(data.value.schedule)) {
    return []
  }

  // Получаем список свободных слотов для выбранной даты
  const freeSlotsForDate = getFreeSlotsForDate(dateStr)

  // В ответе Renovatio рабочие периоды type=1 могут идти после блокировок type=3.
  const schedulesForDate = data.value.schedule.filter(item => {
    return isScheduleForDate(item, dateStr) &&
           isScheduleForSelectedDoctor(item) &&
           isScheduleForSelectedClinic(item)
  })
  const workingSchedules = schedulesForDate.filter(item => Number(item.type) === 1)
  const schedulesToRender = workingSchedules.length > 0 ? workingSchedules : schedulesForDate

  if (schedulesToRender.length === 0) {
    return []
  }

  // Генерируем 5-минутные интервалы в рабочее время
  const slots = []

  schedulesToRender.forEach((scheduleForDate) => {
    // Получаем кабинет из расписания или из данных врача
    const room = scheduleForDate.room || doctorInfo.value.cabinet

    // Парсим время начала и окончания рабочего дня
    const workStart = parseDateTimeString(scheduleForDate.time_start)
    const workEnd = parseDateTimeString(scheduleForDate.time_end)

    if (!workStart || !workEnd) {
      console.warn('Не удалось распарсить время работы:', scheduleForDate)
      return
    }

    let currentTime = new Date(workStart)

    while (currentTime < workEnd) {
      const timeStr = formatTimeToHHMM(currentTime)
      const endTime = new Date(currentTime)
      endTime.setMinutes(endTime.getMinutes() + 5)
      const endTimeStr = formatTimeToHHMM(endTime)

      // Проверяем, свободен ли этот 5-минутный интервал
      const isSlotFree = isTimeSlotFree(timeStr, freeSlotsForDate)

      slots.push({
        id: `${dateStr}_${scheduleForDate.clinic_id ?? 'clinic'}_${slots.length}_${timeStr}`,
        time: timeStr,
        endTime: endTimeStr,
        date: dateStr,
        isBusy: !isSlotFree, // Занят, если не свободен
        isStart: false,
        isEnd: false,
        isInRange: false,
        isHovered: false,
        room: room,
        timestamp: currentTime.getTime()
      })

      currentTime.setMinutes(currentTime.getMinutes() + 5)
    }
  })

  slots.sort((a, b) => a.timestamp - b.timestamp)

  applyClientBookedBusyToSlots(slots, dateStr)
  return slots
}

// При изменении данных с сервера
watch(data, (newData) => {
  if (newData && selectedDate.value) {
    try {
      timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
    } catch (err) {
      console.error('Ошибка при парсинге слотов:', err)
      scheduleError.value = `Ошибка при обработке слотов времени: ${err.message}`
    }
  }
})

// Функция для получения свободных слотов на выбранную дату
const getFreeSlotsForDate = (dateStr) => {
  if (!data.value?.freeSlots) {
    return []
  }

  const selectedDateKey = normalizeDateToKey(dateStr)
  if (!selectedDateKey) return []

  return doctorFreeSlots.value.filter(slot => {
    if (slot?._date && normalizeDateToKey(slot._date) === selectedDateKey) {
      return true
    }

    if (slot?.date) {
      const slotDateKey = normalizeDateToKey(parseDateString(slot.date))
      return slotDateKey === selectedDateKey
    }

    return false
  })
}

// Функция проверки, свободен ли 5-минутный интервал
const isTimeSlotFree = (timeStr, freeSlots) => {
  if (freeSlots.length === 0) return false
  
  // Преобразуем время "HH:MM" в минуты
  const [hours, minutes] = timeStr.split(':').map(Number)
  const timeInMinutes = hours * 60 + minutes
  
  // Проверяем каждый свободный слот
  for (const slot of freeSlots) {
    // Получаем время начала и окончания слота
    const startTimeStr = slot.time_start_short || slot.time.split(' - ')[0]
    const endTimeStr = slot.time_end_short || slot.time.split(' - ')[1]
    
    if (!startTimeStr || !endTimeStr) continue
    
    // Преобразуем в минуты
    const [startHours, startMinutes] = startTimeStr.split(':').map(Number)
    const [endHours, endMinutes] = endTimeStr.split(':').map(Number)
    
    const startInMinutes = startHours * 60 + startMinutes
    const endInMinutes = endHours * 60 + endMinutes
    
    // Проверяем, входит ли наш 5-минутный интервал в свободный слот
    // Учитываем, что свободные слоты по 10 минут, а у нас интервалы по 5 минут
    if (timeInMinutes >= startInMinutes && timeInMinutes + 5 <= endInMinutes) {
      return true
    }
  }
  
  return false
}

// Вспомогательные функции для парсинга дат
const parseDateString = (dateStr) => {
  if (!dateStr) return null
  try {
    // Парсим формат "30.12.2025"
    const [day, month, year] = dateStr.split('.')
    return new Date(year, month - 1, day)
  } catch (error) {
    console.error('Ошибка парсинга даты:', error)
    return null
  }
}

const parseDateTimeString = (dateTimeStr) => {
  if (!dateTimeStr) return null
  try {
    // Парсим формат "30.12.2025 07:00"
    const [datePart, timePart] = dateTimeStr.split(' ')
    const [day, month, year] = datePart.split('.')
    const [hours, minutes] = timePart.split(':')
    return new Date(year, month - 1, day, hours, minutes)
  } catch (error) {
    console.error('Ошибка парсинга даты и времени:', error)
    return null
  }
}

const formatTimeToHHMM = (date) => {
  return date.toTimeString().substring(0, 5)
}

const timeToMinutes = (timeStr) => {
  if (!timeStr) return null
  const [hours, minutes] = timeStr.split(':').map(Number)
  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return null
  return hours * 60 + minutes
}

// Получаем ID врача из URL или из данных
const doctorId = computed(() => {
  // Если в данных есть user_id, используем его
  if (data.value?.schedule?.[0]?.user_id) {
    return data.value.schedule[0].user_id.toString()
  }
  
  // Иначе пробуем извлечь из URL
  const urlParams = new URLSearchParams(window.location.search)
  return urlParams.get('doctor_id') || '27072' // значение по умолчанию
})

// Количество доступных слотов
const availableTimeSlots = computed(() => {
  return timeSlots.value.filter(slot => !slot.isBusy).length
})

// Форматирование времени
const formatTime = (timeStr) => {
  if (!timeStr) return '--:--'
  return timeStr
}

const canFitServiceDurationFromSlot = (startSlot) => {
  if (!selectedServiceDurationMinutes.value) return !startSlot.isBusy
  if (startSlot.isBusy) return false

  const requiredSteps = Math.ceil(selectedServiceDurationMinutes.value / 5)
  if (requiredSteps <= 0) return !startSlot.isBusy

  const startIndex = timeSlots.value.findIndex(s => s.time === startSlot.time)
  if (startIndex < 0) return false

  // Вся услуга + метка конца должны умещаться в сетке рабочего дня (не за пределами последнего слота)
  const endBoundaryIndex = startIndex + requiredSteps
  if (endBoundaryIndex > timeSlots.value.length) {
    return false
  }

  for (let i = startIndex; i < startIndex + requiredSteps; i++) {
    if (!timeSlots.value[i] || timeSlots.value[i].isBusy) {
      return false
    }
  }

  return true
}

const isSlotSelectable = (slot) => {
  if (slot.isBusy) return false
  if (selectedServiceDurationMinutes.value <= 0) return true
  return canFitServiceDurationFromSlot(slot)
}

const isDurationStepStart = (slot) => {
  if (selectedServiceDurationMinutes.value <= 0) return true
  if (!timeSlots.value.length) return false

  const stepMinutes = selectedServiceDurationMinutes.value
  const firstTimestamp = timeSlots.value[0].timestamp
  const diffMinutes = Math.round((slot.timestamp - firstTimestamp) / (1000 * 60))

  return diffMinutes % stepMinutes === 0
}

// Выбор слота
const selectSlot = (slot) => {
  if (selectedServiceDurationMinutes.value > 0) {
    if (!canFitServiceDurationFromSlot(slot)) return

    clearSelection()

    const requiredSteps = Math.ceil(selectedServiceDurationMinutes.value / 5)
    const startIndex = timeSlots.value.findIndex(s => s.time === slot.time)
    const endIndex = startIndex + requiredSteps

    if (startIndex < 0 || endIndex > timeSlots.value.length) return

    selectedStartTime.value = slot.time
    selectedEndTime.value = timeSlots.value[endIndex]?.time ?? timeSlots.value[endIndex - 1]?.endTime

    timeSlots.value[startIndex].isStart = true
    const endMarkerSlot = timeSlots.value[endIndex] ?? timeSlots.value[endIndex - 1]
    if (endMarkerSlot) endMarkerSlot.isEnd = true

    for (let i = startIndex + 1; i < endIndex; i++) {
      timeSlots.value[i].isInRange = true
    }
    return
  }

  if (slot.isBusy) return
  
  if (!selectedStartTime.value) {
    // Сохраняем старые выделения перед очисткой
    const oldStart = selectedStartTime.value
    
    // Очищаем только выделения, но сохраняем начало если оно было
    clearSlotSelection()
    
    // Восстанавливаем начало если оно было
    if (oldStart) {
      const startSlot = timeSlots.value.find(s => s.time === oldStart)
      if (startSlot) startSlot.isStart = true
      selectedStartTime.value = oldStart
    } else {
      // Выбираем новое начало
      selectedStartTime.value = slot.time
      slot.isStart = true
    }
  } else if (!selectedEndTime.value) {
    // Проверяем, что конец позже начала
    const startIndex = timeSlots.value.findIndex(s => s.time === selectedStartTime.value)
    const endIndex = timeSlots.value.findIndex(s => s.time === slot.time)
    
    if (endIndex <= startIndex) {
      // Если кликнули на слот до начала, очищаем выбор
      clearSelection()
      return
    }
    
    // Проверяем, что все слоты диапазона существуют и свободны (нельзя «перепрыгнуть» через занятое)
    let allSlotsFree = true
    for (let i = startIndex; i <= endIndex; i++) {
      if (!timeSlots.value[i] || timeSlots.value[i].isBusy) {
        allSlotsFree = false
        break
      }
    }
    
    if (!allSlotsFree) {
      bookingError.value = {
        type: 'warning',
        title: 'Интервал недоступен',
        message: 'Не все выбранные интервалы свободны. Пожалуйста, выберите другой диапазон.'
      }
      return
    }
    
    // Сохраняем начало
    const startTime = selectedStartTime.value
    
    // Очищаем выделения, но восстанавливаем начало
    clearSlotSelection()
    const startSlot = timeSlots.value.find(s => s.time === startTime)
    if (startSlot) startSlot.isStart = true
    selectedStartTime.value = startTime
    console.log(slot.time);
    // Выбираем конец
    selectedEndTime.value = slot.time
    slot.isEnd = true
    
    // Выделяем промежуточные слоты
    for (let i = startIndex + 1; i < endIndex; i++) {
      timeSlots.value[i].isInRange = true
    }
  } else {
    // Если уже выбрано начало и конец, очищаем и начинаем заново
    clearSelection()
    selectSlot(slot)
  }
}

// Очистка выделения слотов
const clearSlotSelection = () => {
  timeSlots.value.forEach(slot => {
    slot.isStart = false
    slot.isEnd = false
    slot.isInRange = false
  })
}

// Hover эффекты
const hoverSlot = (slot) => {
  if (slot.isBusy) return

  if (selectedServiceDurationMinutes.value > 0) {
    clearHover()

    if (!canFitServiceDurationFromSlot(slot)) return

    const startIndex = timeSlots.value.findIndex(s => s.time === slot.time)
    const requiredSteps = Math.ceil(selectedServiceDurationMinutes.value / 5)
    if (startIndex < 0 || requiredSteps <= 0) return

    let hoverOk = true
    for (let i = startIndex; i < startIndex + requiredSteps; i++) {
      const cell = timeSlots.value[i]
      if (!cell || cell.isBusy) {
        hoverOk = false
        break
      }
      cell.isHovered = true
    }
    if (hoverOk) {
      hoveredSlot.value = slot.time
    }
    return
  }

  hoveredSlot.value = slot.time
  slot.isHovered = true
  
  // Если выбрано начало, показываем возможный диапазон
  if (selectedStartTime.value && !selectedEndTime.value) {
    const startIndex = timeSlots.value.findIndex(s => s.time === selectedStartTime.value)
    const hoverIndex = timeSlots.value.findIndex(s => s.time === slot.time)
    
    if (hoverIndex > startIndex) {
      // Проверяем, все ли слоты до hover свободны
      let allFree = true
      for (let i = startIndex; i <= hoverIndex; i++) {
        if (!timeSlots.value[i] || timeSlots.value[i].isBusy) {
          allFree = false
          break
        }
      }
      
      if (allFree) {
        for (let i = startIndex + 1; i <= hoverIndex; i++) {
          timeSlots.value[i].isHovered = true
        }
      }
    }
  }
}

const clearHover = () => {
  timeSlots.value.forEach(slot => {
    slot.isHovered = false
  })
  hoveredSlot.value = null
}

// Длительность в минутах
const durationInMinutes = computed(() => {
  if (!selectedStartTime.value || !selectedEndTime.value) return 0

  const startMinutes = timeToMinutes(selectedStartTime.value)
  const endMinutes = timeToMinutes(selectedEndTime.value)

  if (startMinutes == null || endMinutes == null || endMinutes <= startMinutes) return 0
  return endMinutes - startMinutes
})

const getPlacementDealId = () => {
  const bx24 = globalThis.BX24
  const placementId = bx24?.placement?.info?.()?.options?.ID
  if (placementId) return placementId

  const params = new URLSearchParams(window.location.search)
  return params.get('ID') || params.get('id') || params.get('deal_id') || 467575
}

// Подтверждение записи
const confirmBooking = async () => {
  // Сброс ошибки бронирования
  bookingError.value = null
  
  const bxId = getPlacementDealId()
  
  // Проверка времени
  if (!selectedStartTime.value || !selectedEndTime.value) {
    bookingError.value = {
      type: 'error',
      title: 'Не указано время записи',
      message: 'Выберите время начала и окончания приема'
    }
    return
  }

  if (!selectedService.value) {
    bookingError.value = {
      type: 'error',
      title: 'Не выбрана услуга',
      message: 'Выберите услугу перед записью'
    }
    return
  }

  try {
    setBookingProgress('Получение сделки из Bitrix24…', 10)
    const deal = await callApi('crm.deal.get', null, null, bxId)
    
    if(!deal.CONTACT_ID){
      bookingError.value = {
        type: 'error', 
        title: 'Не указан контакт в сделке', 
        message: 'Для записи необходимо связать сделку с контактом пациента'
      }
      return
    }
    
    setBookingProgress('Получение данных контакта пациента…', 28)
    const contact = await callApi('crm.contact.get', null, null, deal.CONTACT_ID)

    const missingContactFields = []
    if (!contact.NAME) missingContactFields.push('Имя')
    if (!contact.LAST_NAME) missingContactFields.push('Фамилия')
    if (!contact.BIRTHDATE) missingContactFields.push('Дата рождения')
    
    if (missingContactFields.length > 0) {
      bookingError.value = {
        type: 'error',
        title: 'Не заполнены данные пациента',
        message: `Заполните следующие поля в карточке контакта: ${missingContactFields.join(', ')}`
      }
      return
    }

    setBookingProgress('Проверка обязательных полей контакта…', 42)

    const year = selectedDate.value.getFullYear();
    const month = String(selectedDate.value.getMonth() + 1).padStart(2, '0');
    const day = String(selectedDate.value.getDate()).padStart(2, '0');
    const startDate =  `${year}-${month}-${day} ${selectedStartTime.value}`;
    const endDate =  `${year}-${month}-${day} ${selectedEndTime.value}`;

    const serviceRecord = services.value.find((item) => item.id === selectedService.value)
    const parsePriceForOpportunity = (raw) => {
      if (raw == null || raw === '') return null
      if (typeof raw === 'number' && Number.isFinite(raw)) return raw
      const cleaned = String(raw).replace(/\s/g, '').replace(/[^\d.,-]/g, '').replace(',', '.')
      const n = parseFloat(cleaned)
      return Number.isFinite(n) ? n : null
    }
    const opportunityAmount = serviceRecord ? parsePriceForOpportunity(serviceRecord.price) : null

    const dealUpdateFields = {
      'UF_CRM_1726973347808': startDate,
      'UF_CRM_1762178514': endDate,
      'UF_CRM_1761998673': currentDoctorInfo.value.bxId, //врач
      'UF_CRM_1762175501': selectedClinic.value[0] || null, //клиника
      'UF_CRM_1771593682': serviceRecord?.title || null, //услуга
      ...(opportunityAmount != null ? { OPPORTUNITY: opportunityAmount } : {}),
    }

    const serviceTitleForComment = serviceRecord?.title ?? 'Услуга'
    const servicePriceForComment = serviceRecord?.priceText ?? 'цена не указана'
    const timelineComment =
      `Запись на приём.\nУслуга: ${serviceTitleForComment}\nЦена: ${servicePriceForComment}`

    setBookingProgress('Сохранение даты, врача и клиники в сделке…', 52)
    await callMethod('crm.deal.update', bxId, dealUpdateFields)

    setBookingProgress('Добавление комментария в таймлайн сделки…', 62)
    await callMethodWithParams('crm.timeline.comment.add', {
      fields: {
        ENTITY_ID: Number(bxId),
        ENTITY_TYPE: 'deal',
        COMMENT: timelineComment,
      },
    })

    const serviceIdParam = encodeURIComponent(String(selectedService.value))
    setBookingProgress('Отправка записи в медицинскую систему…', 78)
    const response = await fetch(`https://renovoapp.webtm.ru/index.php?action=torenova&bx_id=${bxId}&service_id=${serviceIdParam}`, {
      method: 'GET',
      mode: 'cors', // Явно указываем режим
      headers: {
        'Accept': 'application/json',
      }
    })
    
    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`)
    }
    
    const result = await response.json()
    console.log('Результат записи:', result)
    
    setBookingProgress('Запись успешно создана', 100)
    await new Promise((resolve) => setTimeout(resolve, 450))

    // Показать успешный алерт
    bookingError.value = {
      type: 'success',
      title: 'Запись подтверждена!',
      message: `Дата: ${selectedDateFormatted.value}\nВремя: ${selectedStartTime.value} - ${selectedEndTime.value}`
    }
    
    // Через 3 секунды скрыть алерт
    setTimeout(() => {
      if (bookingError.value?.type === 'success') {
        bookingError.value = null
      }
    }, 3000)

    const bookedStart = timeSlots.value.find((s) => s.time === selectedStartTime.value)
    const bookedEnd = timeSlots.value.find((s) => s.time === selectedEndTime.value)
    const bookedEndTs = bookedEnd?.timestamp ?? (
      bookedStart ? bookedStart.timestamp + durationInMinutes.value * 60 * 1000 : null
    )
    if (bookedStart && bookedEndTs && selectedDoctor.value) {
      clientBookedBlockedRanges.value.push({
        doctorId: String(selectedDoctor.value),
        dateKey: toDateKey(selectedDate.value),
        startTs: bookedStart.timestamp,
        endTs: bookedEndTs,
      })
    }
    if (data.value && selectedDate.value) {
      timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
    }

    clearSelection()
    
  } catch (error) {
    console.error('Ошибка при подтверждении записи:', error)
    bookingError.value = {
      type: 'error',
      title: 'Ошибка записи',
      message: error.message || 'Не удалось завершить запись',
      details: {
        error: error.toString(),
        stack: error.stack,
        timestamp: new Date().toISOString()
      }
    }
  } finally {
    resetBookingProgress()
  }
}

// Заголовок для слота
const getSlotTitle = (slot) => {
  if (slot.isBusy) {
    return `Занято\n${slot.time} - ${slot.endTime}\n${slot.room ? slot.room : ''}`
  }

  if (!isSlotSelectable(slot) && selectedServiceDurationMinutes.value > 0) {
    return `Свободно\n${slot.time} - ${slot.endTime}\nНедостаточно времени для выбранной услуги\n${slot.room ? slot.room : ''}`
  }

  return `${slot.time} - ${slot.endTime}\n${slot.room ? slot.room : ''}`
}

// Часы для отображения
const compactHours = computed(() => {
  if (timeSlots.value.length === 0) return []
  
  const hoursSet = new Set()
  timeSlots.value.forEach(slot => {
    const hour = parseInt(slot.time.split(':')[0])
    hoursSet.add(hour)
  })
  
  return Array.from(hoursSet).sort((a, b) => a - b)
})

// Ширина для заголовков часов
const hourSpanWidth = computed(() => {
  if (compactHours.value.length === 0) return '0px'
  return `${100 / compactHours.value.length * 4}%`
})

// Шкала минут
const minutesScale = computed(() => {
  return Array.from({ length: 12 }, (_, i) => i * 5)
})

// Видимые слоты
const visibleTimeSlots = computed(() => {
  return timeSlots.value
})

// Элементы легенды
const legendItems = [
  { label: 'Свободно', class: 'free' },
  { label: 'Занято', class: 'busy' },
  { label: 'Начало', class: 'start' },
  { label: 'Конец', class: 'end' },
  { label: 'Выбрано', class: 'selected' },
]

// 1. Добавляем реактивные переменные
const doctorSchedule = ref(null)
const loadingSchedule = ref(false)

// Инициализация при монтировании
onMounted(async() => {
  try {
    setTodayDate()
    await fetchClinics()
    await loadServicesIndex()
  } catch (err) {
    console.error('Ошибка при инициализации:', err)
    apiError.value = err.message || 'Ошибка при загрузке начальных данных'
  }
})

onUnmounted(() => {
  if (loadScheduleTimeout) {
    clearTimeout(loadScheduleTimeout)
  }
})

const hourHeaders = computed(() => {
  if (timeSlots.value.length === 0) return []
  
  // Группируем слоты по часам
  const hoursMap = new Map()
  
  timeSlots.value.forEach(slot => {
    const hour = parseInt(slot.time.split(':')[0])
    if (!hoursMap.has(hour)) {
      hoursMap.set(hour, {
        hour: hour,
        startIndex: timeSlots.value.findIndex(s => s.time.startsWith(`${hour}:`)),
        cellCount: 0
      })
    }
    hoursMap.get(hour).cellCount++
  })
  
  // Преобразуем в массив и сортируем по часу
  const hoursArray = Array.from(hoursMap.values())
    .sort((a, b) => a.hour - b.hour)
  
  // Рассчитываем ширину для каждого часа
  const totalCells = timeSlots.value.length
  hoursArray.forEach(hourInfo => {
    // Процент ширины, который должен занимать этот час
    hourInfo.widthPercentage = (hourInfo.cellCount / totalCells) * 100
    // Нормализуем ширину ячейки (каждая ячейка = 5 минут)
    hourInfo.cellWidth = hourInfo.cellCount
  })
  
  return hoursArray
})

// Ширина для заголовков часов
const timeTableWidth = computed(() => {
  if (visibleTimeSlots.value.length === 0) return '0px'
  
  // Предполагаем, что каждая ячейка имеет базовую ширину
  const baseCellWidth = 16 // в пикселях
  const totalCells = visibleTimeSlots.value.length
  const totalWidth = totalCells * baseCellWidth
  
  return `${totalWidth}px`
})
</script>

<style>
/* Цепочка высоты: скролл приложения во встроенном iframe */
html,
body,
#__nuxt {
  height: 100%;
  margin: 0;
}

.v-col-6 {
  max-width: 22rem !important;
}

/* Корень приложения: вертикальный скролл (в т.ч. во встроенном окне Bitrix24) */
.booking-app-scroll {
  box-sizing: border-box;
  width: 100%;
  min-height: 100%;
  max-height: 100%;
}

.h-100 {
  height: 100%;
}

.time-table-container {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  background-color: #fff;
}

.time-legend {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
}

.legend-item {
  display: flex;
  align-items: center;
  margin-right: 8px;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  margin-right: 4px;
  border: 1px solid #ddd;
}

.legend-color.free {
  background-color: #e8f5e9;
}

.legend-color.busy {
  background-color: #f5f5f5;
}

.legend-color.start {
  background-color: #4caf50;
  border-color: #2e7d32;
}

.legend-color.end {
  background-color: #f44336;
  border-color: #c62828;
}

.legend-color.selected {
  background-color: #bbdefb;
  border-color: #1976d2;
}

.legend-color.hover {
  background-color: #e3f2fd;
}

.time-table-wrapper {
  display: flex;
  position: relative;
  overflow-x: auto;
}

.hour-labels {
  display: flex;
  flex-direction: column;
  width: 60px;
  min-width: 60px;
  margin-top: 24px;
}

.hour-label {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding-right: 12px;
  font-size: 12px;
  color: #666;
  border-top: 1px solid #e0e0e0;
}

.time-grid {
  display: flex;
  flex-wrap: wrap;
  flex-grow: 1;
  position: relative;
}

.time-slot {
  width: 20px;
  height: 60px;
  border: 1px solid #e0e0e0;
  border-left: none;
  border-bottom: none;
  cursor: pointer;
  position: relative;
  transition: background-color 0.2s;
}

.time-slot:first-child {
  border-left: 1px solid #e0e0e0;
}

.time-slot.free {
  background-color: #e8f5e9;
}

.time-slot.busy {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.time-slot.start-slot {
  background-color: #4caf50 !important;
  border-color: #2e7d32 !important;
  z-index: 3;
}

.time-slot.end-slot {
  background-color: #f44336 !important;
  border-color: #c62828 !important;
  z-index: 3;
}

.time-slot.in-range {
  background-color: #bbdefb;
  border-color: #1976d2;
  z-index: 2;
}

.time-slot.hovered {
  background-color: #e3f2fd;
}

.time-slot:hover:not(.busy) {
  background-color: #c8e6c9;
}

.time-label {
  position: absolute;
  top: -24px;
  left: 0;
  right: 0;
  text-align: center;
  font-size: 11px;
  color: #666;
}

.quarter-mark {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #bdbdbd;
}

.quarter-mark.hour-mark {
  height: 3px;
  background-color: #757575;
}

.start-marker {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 20px;
  height: 20px;
  background-color: #2e7d32;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.end-marker {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 20px;
  height: 20px;
  background-color: #c62828;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.time-axis {
  display: flex;
  justify-content: space-between;
  margin-top: 32px;
  padding: 0 20px;
  position: relative;
}

.axis-label {
  font-size: 11px;
  color: #666;
  position: relative;
}

.axis-label::before {
  content: '';
  position: absolute;
  top: -20px;
  left: 50%;
  width: 1px;
  height: 15px;
  background-color: #bdbdbd;
}

.axis-label:nth-child(1) { width: 20px; }
.axis-label:nth-child(2) { width: 60px; }
.axis-label:nth-child(3) { width: 120px; }
.axis-label:nth-child(4) { width: 240px; }

/* Стили для скроллбара */
.time-table-wrapper::-webkit-scrollbar {
  height: 8px;
}

.time-table-wrapper::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.time-table-wrapper::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.time-table-wrapper::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
/* Компактная легенда */
.compact-legend {
  background: #f8f9fa;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #e9ecef;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.legend-marker {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  border: 1px solid #dee2e6;
}

.legend-marker.free { background-color: #d4edda; border-color: #c3e6cb; }
.legend-marker.busy { background-color: #f8d7da; border-color: #f5c6cb; }
.legend-marker.start { background-color: #28a745; border-color: #1e7e34; }
.legend-marker.end { background-color: #dc3545; border-color: #bd2130; }
.legend-marker.selected { background-color: #007bff; border-color: #0056b3; }
.legend-marker.hover { background-color: #17a2b8; border-color: #117a8b; }

/* Компактная таблица времени */
.compact-time-table {
  overflow-x: scroll !important;
  overflow-y: hidden !important;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  width: 100%;
  max-width: 47rem;
}

.time-header {
  display: flex;
  background: #f8f9fa;
  border-bottom: 1px solid #e0e0e0;
  width: var(--table-width, auto);
  min-width: 100%;
}

.hour-header {
  padding: 8px 4px;
  text-align: center;
  font-size: 12px;
  font-weight: 500;
  color: #495057;
  border-right: 1px solid #e0e0e0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  overflow: hidden;
  white-space: nowrap;
}

.hour-header:last-child {
  border-right: none;
}

.time-grid-compact {
  display: flex;
  position: relative;
  height: 3.5rem;
  margin: 0 0 20px 0;
}

.minute-scale {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 100%;
  display: flex;
  pointer-events: none;
}

.minute-mark {
  flex: 1;
  border-right: 1px solid #f0f0f0;
  position: relative;
}

.minute-mark.major-mark::after {
  content: '';
  position: absolute;
  bottom: 0;
  right: 0;
  width: 1px;
  height: 6px;
  background: #adb5bd;
}

.time-cell {
  flex: 1;
  height: 100%;
  border-right: 1px solid #f0f0f0;
  cursor: pointer;
  position: relative;
  transition: all 0.15s ease;
  margin: 0;
  min-width: 1rem;
}

.booking-toolbar-card{
  display: flex;
  flex-wrap: nowrap;
}

.booking-calendar-column{
  padding: 0 !important;
}

.v-col {
  padding-right: 0 !important;
}

.time-cell:last-child {
  border-right: none;
}

.time-cell:hover:not(.busy-cell) {
  transform: scaleY(1.1);
  z-index: 2;
}

.free-cell {
  background: linear-gradient(to bottom, #e8f5e9, #d4edda);
}

.limited-cell {
  cursor: not-allowed;
  background: linear-gradient(to bottom, #edf8ee, #dcefe0);
}

.busy-cell {
  background: linear-gradient(to bottom, #f5f5f5, #e9ecef);
  cursor: not-allowed;
  opacity: 0.7;
}

.start-cell {
  background: linear-gradient(to bottom, #28a745, #1e7e34) !important;
  z-index: 3;
}

.end-cell {
  background: linear-gradient(to bottom, #dc3545, #bd2130) !important;
  z-index: 3;
}

.selected-cell {
  background: linear-gradient(to bottom, #cce5ff, #b3d7ff);
  z-index: 2;
}

.hover-cell {
  background: linear-gradient(to bottom, #e3f2fd, #d1e9ff);
  z-index: 1;
}

.cell-marker {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.cell-marker.start {
  background: #1e7e34;
}

.cell-marker.end {
  background: #bd2130;
}

/* Компактная шкала */
.compact-scale {
  display: flex;
  height: 24px;
  background: #f8f9fa;
  border-top: 1px solid #e0e0e0;
  position: relative;
}

.scale-mark {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: #6c757d;
  border-right: 1px solid #dee2e6;
  position: relative;
}

.scale-mark::before {
  content: '';
  position: absolute;
  top: -4px;
  left: 0;
  right: 0;
  height: 4px;
  background: #adb5bd;
}

.scale-mark:last-child {
  border-right: none;
}

/* Временные отображения */
.time-display {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  background: white;
  border-radius: 4px;
  border: 1px solid #e9ecef;
}

.selected-interval-content {
  flex-wrap: wrap;
  gap: 12px;
}

.selected-interval-main {
  flex: 1 1 260px;
  min-width: 0;
}

.selected-interval-title,
.selected-interval-times,
.confirm {
  flex-wrap: wrap;
}

.selected-interval-title {
  gap: 4px 8px;
}

.selected-interval-times {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.selected-interval-times .time-display {
  justify-content: space-between;
  margin: 0 !important;
  min-width: 0;
}

.selected-interval-arrow,
.selected-interval-divider {
  display: none;
}

/* Адаптивность */
@media (max-width: 960px) {
  .compact-time-table {
    font-size: 11px;
  }
  
  .hour-header {
      padding: 4px 2px;
      font-size: 11px;
      min-height: 35px;
    }

  /* Для очень коротких интервалов */
  .hour-header .text-caption {
    font-size: 9px;
    line-height: 1;
    margin-top: 2px;
  }
  .time-grid-compact {
    height: 40px;
  }
  
  .cell-marker {
    width: 16px;
    height: 16px;
  }
}

/* Плавные переходы */
.v-card {
  transition: all 0.3s ease;
}

.v-btn {
  transition: all 0.2s ease;
}

.v-container {
  max-width: none !important;
  padding: 0 !important;
}

.v-container .v-card {
  max-width: none !important;
  padding: 0.5rem !important;
}

.calendar {
  padding: 0.7rem !important;
}

.v-text-field .v-input__details {
  display: none !important;
}

.v-date-picker-month{
  padding: 0 !important;
  margin-top: 0rem;
}

.v-date-picker{
  width: unset;
}

#__nuxt .booking-schedule-block .booking-schedule-card {
  margin-top: 0;
}

.slot-info {
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: white;
  padding: 2px 4px;
  border-radius: 2px;
  border: 1px solid #e0e0e0;
  font-size: 10px;
  white-space: nowrap;
  z-index: 10;
  opacity: 0;
  transition: opacity 0.2s;
}

.time-cell:hover .slot-info {
  opacity: 1;
}

.confirm {
  align-self: end;
}

.booking-progress-panel {
  background: rgba(var(--v-theme-primary), 0.06);
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
}

.booking-progress-text {
  transition: opacity 0.28s ease;
}

.booking-app-container{
  display: flex;
}

.booking-progress-track {
  position: relative;
  height: 10px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(var(--v-theme-primary), 0.13);
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
}

.booking-progress-fill {
  position: relative;
  height: 100%;
  min-width: 8px;
  overflow: hidden;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    rgb(var(--v-theme-primary)) 0%,
    rgba(var(--v-theme-primary), 0.68) 45%,
    rgb(var(--v-theme-primary)) 100%
  );
  transition: width 0.45s ease;
}

.booking-progress-fill::after {
  content: '';
  position: absolute;
  inset: 0;
  width: 100%;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255, 255, 255, 0.38) 50%,
    transparent 100%
  );
  animation: booking-progress-shimmer 1.8s ease-in-out infinite;
}

.booking-progress-percent {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(255, 255, 255, 0.95);
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

@keyframes booking-progress-shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.booking-layout {
  align-items: stretch;
}

/* Верхняя карточка: фильтры + календарь в одном ряду на md+ */
@media (min-width: 960px) {
  .booking-toolbar-row {
    flex-wrap: nowrap !important;
  }

  .booking-toolbar-row > .v-col {
    min-width: 0 !important;
  }

  .calendar-sidebar {
    position: sticky;
    top: 12px;
  }
}

.booking-schedule-block {
  min-width: 32rem;

}

.booking-schedule-card {
  min-height: 0;
  width: 100%;
}

.booking-schedule-card__body {
  min-width: 0;
}

.booking-toolbar-card {
  display: flex;
  flex-wrap: nowrap;
  overflow-x: scroll;
}

.booking-toolbar-row {
  min-width: 45.5rem;
  flex-wrap: nowrap;
  overflow-x: scroll;
}

.select-text {
  font-size: 16px;
}

.booking-calendar-column {
  align-self: flex-start;
}

th,
.v-table th,
.v-data-table th,
.v-data-table-header__content {
  text-align: center !important;
  justify-content: center !important;
}
</style>