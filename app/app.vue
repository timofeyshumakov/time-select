<template>
  <v-container>
<!-- Фильтры клиники и врача -->
    <v-row>
      <v-col cols="12" md="6">
        <v-autocomplete
          v-model="selectedClinic"
          :items="clinics"
          item-title="title"
          item-value="id"
          label="Выберите клинику"
          variant="outlined"
          @update:model-value="onClinicChange"
          clearable
          :loading="loadingClinics"
        >
          <template v-slot:prepend>
            <v-icon>mdi-hospital-building</v-icon>
          </template>
        </v-autocomplete>
      </v-col>

      <v-col cols="12" md="6">
        <v-autocomplete
          v-model="selectedDoctor"
          :items="filteredDoctors"
          item-title="title"
          item-value="ufCrm7Renovatioid"
          label="Выберите врача"
          variant="outlined"
          @update:model-value="onDoctorChange"
          :disabled="!selectedClinic || loadingDoctors"
          :loading="loadingDoctors"
          clearable
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

    <!-- Информация о выбранном враче -->
    <v-row v-if="currentDoctorInfo" class="mb-4">
      <v-col cols="12">
        <v-card v-if="currentDoctorInfo" class="mb-4" elevation="0" color="primary-lighten-5">
            <v-card-text class="pa-3">
              <div class="d-flex align-center">
                <v-avatar class="mr-3" color="primary" size="48">
                  <v-icon size="24">mdi-doctor</v-icon>
                </v-avatar>
                <div>
                  <div class="text-h6 text-primary">{{ currentDoctorInfo.name }}</div>
                  <div class="text-subtitle-2">{{ currentDoctorInfo.ufCrm7Profession }}</div>
                  <div class="text-caption text-grey">
                    <v-icon size="14" class="mr-1">mdi-door</v-icon>
                    {{ currentDoctorInfo.cabinet }}
                  </div>
                </div>
              </div>
            </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Состояния загрузки и ошибки -->
    <v-row v-if="pending">
      <v-col cols="12" class="text-center">
        <v-progress-circular
          indeterminate
          color="primary"
          size="64"
        ></v-progress-circular>
        <p class="mt-4">Загрузка расписания...</p>
      </v-col>
    </v-row>

    <v-row v-else-if="error">
      <v-col cols="12">
        <v-alert type="error" prominent>
          <v-alert-title>Ошибка загрузки</v-alert-title>
          {{ error.message }}
        </v-alert>
        <v-btn @click="refresh" color="primary" class="mt-4">
          Попробовать снова
        </v-btn>
      </v-col>
    </v-row>

    <!-- Основной контент -->
    <v-row v-else class="main">
      <!-- Календарь -->
      <v-col cols="12" md="4" >
        <v-card elevation="2" class="pa-4 calendar">
          <v-date-picker
            v-model="selectedDate"
            :min="minDate"
            :max="maxDate"
            :allowed-dates="availableDates"
            locale="ru-RU"
            color="primary"
            header-color="primary"
            @update:model-value="onDateChange"
            full-width
          ></v-date-picker>
          <div v-if="!selectedDoctor" class="text-center py-4">
            <v-icon size="48" color="grey" class="mb-2">mdi-account-question</v-icon>
            <p class="text-grey">Выберите врача для отображения расписания</p>
          </div>
        </v-card>
      </v-col>

      <!-- Таблица времени (5-минутные интервалы) -->
      <v-col cols="12" md="8">
      <v-card elevation="2" class="pa-4 h-100">
        <v-card-title class="text-h6 d-flex align-center">
          <v-icon class="mr-2">mdi-clock-outline</v-icon>
          {{ selectedDateFormatted ? `Расписание на ${selectedDateFormatted}` : 'Выберите дату' }}
        </v-card-title>
        
        <div v-if="!selectedDate" class="text-center py-8">
          <v-icon size="64" color="grey">mdi-calendar</v-icon>
          <p class="mt-4 text-grey">Выберите дату в календаре</p>
        </div>

        <div v-else-if="timeSlots.length === 0" class="text-center py-8">
          <v-icon size="64" color="grey">mdi-clock-off-outline</v-icon>
          <p class="mt-4 text-grey">На выбранную дату нет доступного времени</p>
          <v-btn @click="selectNextAvailableDate" color="primary" variant="outlined" class="mt-4">
            Выбрать следующую доступную дату
          </v-btn>
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
            <div class="time-header">
              <div 
                v-for="hour in compactHours" 
                :key="hour"
                class="hour-header"
                :style="{ width: hourSpanWidth }"
              >
                {{ hour }}:00
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
                    {{ slot.room }}
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
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="d-flex align-center mb-1">
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
                    ></v-text-field>
                    
                    <v-icon size="16" color="grey">mdi-arrow-right</v-icon>
                    
                    <v-text-field
                      v-model="editableEndTime"
                      label="Конец"
                      type="time"
                      variant="outlined"
                      density="compact"
                      hide-details
                      style="max-width: 120px"
                      :rules="[timeValidation]"
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
                  <div v-else class="d-flex align-center text-body-2">
                    <div class="time-display mr-4">
                      <span class="text-grey">Начало:</span>
                      <span class="ml-2 font-weight-medium">{{ selectedStartTime ? formatTime(selectedStartTime) : '--:--' }}</span>
                    </div>
                    
                    <v-icon size="16" color="grey">mdi-arrow-right</v-icon>
                    
                    <div class="time-display mx-4">
                      <span class="text-grey">Конец:</span>
                      <span class="ml-2 font-weight-medium">{{ selectedEndTime ? formatTime(selectedEndTime) : '--:--' }}</span>
                    </div>
                    
                    <v-divider vertical class="mx-2" />
                    
                    <div class="time-display ml-4">
                      <span class="text-grey">Длительность:</span>
                      <span class="ml-2 font-weight-medium">
                        {{ durationInMinutes > 0 ? `${durationInMinutes} мин` : '--' }}
                      </span>
                    </div>
                  </div>
                </div>
                
                <div class="d-flex gap-2 confirm">
                  <v-btn 
                    v-if="selectedStartTime && selectedEndTime && !isEditingTime"
                    color="success"
                    size="small"
                    @click="confirmBooking"
                    class="px-4"
                  >
                    <v-icon size="18" class="mr-1">mdi-check</v-icon>
                    Записаться
                  </v-btn>
                  
                  <v-btn 
                    variant="text"
                    size="small"
                    @click="clearSelection"
                    :disabled="!selectedStartTime && !selectedEndTime"
                  >
                    <v-icon size="18">mdi-close</v-icon>
                  </v-btn>
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
          <v-alert
            v-if="alertTitle"
            :type="alertType"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="showAlert('error', '')"
          >
            <template v-slot:title>
              {{ alertTitle }}
            </template>
            <div v-if="alertDescription" class="mt-2">
              {{ alertDescription }}
            </div>
          </v-alert>
        </div>
      </v-card>
    </v-col>
    </v-row>
  </v-container>
</template>
<script setup>

import { ref, computed, onMounted, watch } from 'vue'
import { callApi, callMethod } from './callApi'

// Реактивные переменные
const selectedClinic = ref(null)
const selectedDoctor = ref(null)
const loadingClinics = ref(false)
const loadingDoctors = ref(false)
const isEditingTime = ref(false)
const editableStartTime = ref('')
const editableEndTime = ref('')
const selectedDate = ref(null)
const timeSlots = ref([])
const selectedStartTime = ref(null)
const selectedEndTime = ref(null)
const hoveredSlot = ref(null)
const selectedClinicRenovatioId = computed(() => {
  if (!selectedClinic.value) return null
  
  const clinic = clinics.value.find(c => c.id == selectedClinic.value)
  return clinic?.ufCrm9Renovatioid
})
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

// Метод применения изменений времени
const applyTimeEdit = () => {
  if (!isTimeEditValid.value) return
  
  // Находим соответствующие слоты
  const startSlot = timeSlots.value.find(s => s.time === editableStartTime.value)
  const endSlot = timeSlots.value.find(s => s.time === editableEndTime.value)
  
  if (!startSlot || !endSlot) {
    alert('Указанное время не найдено в расписании')
    return
  }
  
  // Проверяем, что слоты свободны
  if (startSlot.isBusy || endSlot.isBusy) {
    alert('Выбранное время занято')
    return
  }
  
  const startIndex = timeSlots.value.findIndex(s => s.time === editableStartTime.value)
  const endIndex = timeSlots.value.findIndex(s => s.time === editableEndTime.value)
  
  // Проверяем порядок времени
  if (endIndex <= startIndex) {
    alert('Время окончания должно быть позже времени начала')
    return
  }
  
  // Проверяем, что все промежуточные слоты свободны
  let allSlotsFree = true
  for (let i = startIndex; i <= endIndex; i++) {
    if (timeSlots.value[i].isBusy) {
      allSlotsFree = false
      break
    }
  }
  
  if (!allSlotsFree) {
    alert('Не все выбранные интервалы свободны')
    return
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
  
  return endIndex > startIndex
})

// Очистка выбора (добавьте сброс редактирования)
const clearSelection = () => {
  selectedStartTime.value = null
  selectedEndTime.value = null
  isEditingTime.value = false
  clearSlotSelection()
}

// При изменении даты тоже сбрасываем редактирование
const onDateChange = (date) => {
  selectedDate.value = date
  timeSlots.value = parseScheduleToTimeSlots(date)
  clearSelection()
  isEditingTime.value = false
}

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

const onDoctorChange = (doctorId) => {
  selectedDoctor.value = doctorId
  updateDoctorInfo()
  
  if (doctorId) {
    fetchDoctorSchedule(doctorId)
  } else {
    data.value = null
    timeSlots.value = []
  }
}
const url = computed(() => {
  if (!selectedDoctor.value) return null
  
  const clinicId = selectedClinicRenovatioId.value
  
  return `https://renovoapp.webtm.ru/index.php?action=get_calendar&doctor_id=${selectedDoctor.value}&clinic_id=${clinicId}`
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
const clinics = ref([]);

const allDoctors = ref([]);
// Отфильтрованные врачи по клинике
const filteredDoctors = computed(() => {

  if (!selectedClinic.value) return []
  return allDoctors.value.filter(doctor => doctor.ufCrm7Clinics == selectedClinic.value)
})

// Информация о выбранном враче
const updateDoctorInfo = () => {

}

// вызов API для получения клиник
const fetchClinics = async () => {
  loadingClinics.value = true
  try {
    const response = await callApi('crm.item.list', {}, ["id", "title", "ufCrm9Renovatioid"], 1044);
    await fetchDoctors();
    console.log(allDoctors.value);
    // Фильтруем клиники: оставляем только те, у которых есть врачи
    clinics.value = response.filter(clinic => {
      const hasDoctors = allDoctors.value.some(doctor => 
        doctor.ufCrm7Clinics == clinic.id
      );
      return hasDoctors;
    });
  } catch (error) {
    console.error('Ошибка загрузки клиник:', error)
  } finally {
    loadingClinics.value = false
  }
}

// Моковый вызов API для получения врачей
const fetchDoctors = async () => {
  loadingDoctors.value = true
  try {
    const response = await callApi('crm.item.list', {}, ["id", "title", "ufCrm7Clinics", 'ufCrm7Profession', 'ufCrm7Renovatioid'], 1040);
    allDoctors.value = response;
  } catch (error) {
    console.error('Ошибка загрузки врачей:', error)
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
const maxDate = ref(() => {
  const date = new Date()
  date.setMonth(date.getMonth() + 3)
  return date.toISOString().split('T')[0]
})

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

// Функция для парсинга графика из data.schedule
const parseScheduleToTimeSlots = (dateStr) => {
  if (!data.value?.schedule || !Array.isArray(data.value.schedule)) {
    return []
  }
  
  // Находим расписание для выбранной даты
  const scheduleForDate = data.value.schedule.find(item => {
    const itemDate = parseDateString(item.date)
    const selected = new Date(dateStr)
    return itemDate && selected && 
           itemDate.getDate() === selected.getDate() &&
           itemDate.getMonth() === selected.getMonth() &&
           itemDate.getFullYear() === selected.getFullYear()
  })
  
  if (!scheduleForDate) {
    return []
  }
  
  // Получаем кабинет из расписания или из данных врача
  const room = scheduleForDate.room || doctorInfo.value.cabinet
  
  // Парсим время начала и окончания рабочего дня
  const workStart = parseDateTimeString(scheduleForDate.time_start)
  const workEnd = parseDateTimeString(scheduleForDate.time_end)
  
  if (!workStart || !workEnd) {
    return []
  }
  
  // Получаем список свободных слотов для выбранной даты
  const freeSlotsForDate = getFreeSlotsForDate(dateStr)
  
  // Генерируем 5-минутные интервалы в рабочее время
  const slots = []
  let currentTime = new Date(workStart)
  
  while (currentTime < workEnd) {
    const timeStr = formatTimeToHHMM(currentTime)
    const endTime = new Date(currentTime)
    endTime.setMinutes(endTime.getMinutes() + 5)
    const endTimeStr = formatTimeToHHMM(endTime)
    
    // Проверяем, свободен ли этот 5-минутный интервал
    const isSlotFree = isTimeSlotFree(timeStr, freeSlotsForDate)
    
    slots.push({
      id: `${dateStr}_${timeStr}`,
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
  
  return slots
}

// При изменении данных с сервера
watch(data, (newData) => {
  if (newData && selectedDate.value) {
    timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
  }
})

// Функция для получения свободных слотов на выбранную дату
const getFreeSlotsForDate = (dateStr) => {
  if (!data.value?.freeSlots || !data.value.freeSlots[doctorId.value]) {
    return []
  }
  
  // Преобразуем dateStr в формат "30.12.2025" для сравнения
  const date = new Date(dateStr)
  const formattedDate = `${String(date.getDate()).padStart(2, '0')}.${String(date.getMonth() + 1).padStart(2, '0')}.${date.getFullYear()}`
  // Фильтруем свободные слоты для выбранной даты
  return data.value.freeSlots[doctorId.value].filter(slot => {
    // Сравниваем даты в формате "30.12.2025"
    return slot.date === formattedDate || slot._date === dateStr
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

// При изменении данных с сервера
watch(data, (newData) => {
  if (newData && selectedDate.value) {
    timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
  }
})

// Количество доступных слотов
const availableTimeSlots = computed(() => {
  return timeSlots.value.filter(slot => !slot.isBusy).length
})

// Доступные даты для выбора
const availableDates = computed(() => {
  if (!data.value?.schedule) return []
  
  const dates = data.value.schedule
    .map(item => {
      const date = parseDateString(item.date)
      return date ? date.toISOString().split('T')[0] : null
    })
    .filter(date => date !== null)
    
  return [...new Set(dates)]
})

// Выбор следующей доступной даты
const selectNextAvailableDate = () => {
  if (availableDates.value.length > 0) {
    const today = new Date().toISOString().split('T')[0]
    const futureDates = availableDates.value.filter(date => date >= today)
    
    if (futureDates.length > 0) {
      selectedDate.value = futureDates[0]
      timeSlots.value = parseScheduleToTimeSlots(futureDates[0])
    }
  }
}

// Форматирование времени
const formatTime = (timeStr) => {
  if (!timeStr) return '--:--'
  return timeStr
}

// Выбор слота
const selectSlot = (slot) => {
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
    
    // Проверяем, что все промежуточные слоты свободны
    let allSlotsFree = true
    for (let i = startIndex; i <= endIndex; i++) {
      if (timeSlots.value[i].isBusy) {
        allSlotsFree = false
        break
      }
    }
    
    if (!allSlotsFree) {
      alert('Не все выбранные интервалы свободны. Пожалуйста, выберите другой диапазон.')
      return
    }
    
    // Сохраняем начало
    const startTime = selectedStartTime.value
    
    // Очищаем выделения, но восстанавливаем начало
    clearSlotSelection()
    const startSlot = timeSlots.value.find(s => s.time === startTime)
    if (startSlot) startSlot.isStart = true
    selectedStartTime.value = startTime
    
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
        if (timeSlots.value[i].isBusy) {
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
  
  const startSlot = timeSlots.value.find(s => s.time === selectedStartTime.value)
  const endSlot = timeSlots.value.find(s => s.time === selectedEndTime.value)
  
  if (!startSlot || !endSlot) return 0
  
  const durationMs = endSlot.timestamp - startSlot.timestamp
  return Math.round(durationMs / (1000 * 60))
})
const alertType = ref('error') // 'error', 'warning', 'info', 'success'
const alertTitle = ref('')
const alertDescription = ref('')

const showAlert = (type, title, description = '') => {
  alertType.value = type
  alertTitle.value = title
  alertDescription.value = description
}

// Подтверждение записи
const confirmBooking = async () => {
  const bxId = 467575;
  // Сброс алертов
  showAlert('error', '')
  
  // Проверка времени
  if (!selectedStartTime.value || !selectedEndTime.value) {
    showAlert('error', 'Не указано время записи', 'Выберите время начала и окончания приема')
    return
  }

  try {
    const deal = await callApi('crm.deal.get', null, null, bxId);
    
    if(!deal.CONTACT_ID){
      showAlert(
        'error', 
        'Не указан контакт в сделке', 
        'Для записи необходимо связать сделку с контактом пациента'
      )
      return;
    }
    
    const contact = await callApi('crm.contact.get', null, null, deal.CONTACT_ID);

    const missingContactFields = []
    if (!contact.NAME) missingContactFields.push('Имя')
    if (!contact.LAST_NAME) missingContactFields.push('Фамилия')
    if (!contact.BIRTHDATE) missingContactFields.push('Дата рождения')
    
    if (missingContactFields.length > 0) {
      showAlert(
        'error',
        'Не заполнены данные пациента',
        `Заполните следующие поля в карточке контакта: ${missingContactFields.join(', ')}`
      )
      return;
    }
    console.log(currentDoctorInfo);
    const dealUpdateFields = {
      'UF_CRM_1726973347808': selectedStartTime.value,
      'UF_CRM_1762178514': selectedEndTime.value,
      'UF_CRM_1761998673': currentDoctorInfo.value.bxId, //врач
      'UF_CRM_1762175501': selectedClinic.value, //клиника
    };

    await callMethod('crm.deal.update', bxId, dealUpdateFields);
    const response = await fetch(`https://renovoapp.webtm.ru/index.php?action=torenova&bx_id=${bxId}`, {
      method: 'GET',
      mode: 'cors', // Явно указываем режим
      headers: {
        'Accept': 'application/json',
      }
    })
    
    // Показать успешный алерт
    showAlert(
      'success',
      'Запись подтверждена!',
      `Дата: ${selectedDateFormatted.value}\nВремя: ${selectedStartTime.value} - ${selectedEndTime.value}`
    )
    
    // Через 3 секунды скрыть алерт
    setTimeout(() => {
      showAlert('success', '')
    }, 3000)
    
    clearSelection()
    
  } catch (error) {
    console.error('Ошибка при подтверждении записи:', error)
    showAlert(
      'error',
      'Ошибка загрузки данных',
      ''
    )
  }
}

// Заголовок для слота
const getSlotTitle = (slot) => {
  if (slot.isBusy) {
    return `Занято\n${slot.time} - ${slot.endTime}\n${slot.room}`
  }
  return `${slot.time} - ${slot.endTime}\n${slot.room}`
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

// 2. Создаем функцию загрузки расписания
const fetchDoctorSchedule = async (doctorId) => {
  if (!doctorId) {
    data.value = null
    return
  }
  
  loadingSchedule.value = true
  pending.value = true
  error.value = null
  
  try {
    const clinicId = selectedClinicRenovatioId.value;
    // Формируем URL для запроса расписания
    const url = `https://renovoapp.webtm.ru/index.php?action=get_calendar&doctor_id=${doctorId}&clinic_id=${clinicId}`
    
    // Выполняем запрос
    const response = await fetch(url, {
      method: 'GET',
      mode: 'cors', // Явно указываем режим
      headers: {
        'Accept': 'application/json',
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    const result = await response.json()
    // Сохраняем данные
    data.value = {
      schedule: result.schedule || [],
      freeSlots: result.freeSlots || {},
    }

    // Если есть выбранная дата, обновляем слоты
    if (selectedDate.value) {
      timeSlots.value = parseScheduleToTimeSlots(selectedDate.value)
    }
    
  } catch (err) {
    console.error('Ошибка загрузки расписания:', err)
    error.value = {
      message: `Не удалось загрузить расписание: ${err.message}`
    }
    data.value = null
  } finally {
    loadingSchedule.value = false
    pending.value = false
  }
}

// 3. Добавляем watch для отслеживания выбора врача
watch(selectedDoctor, (newDoctorId) => {
  if (newDoctorId) {
    fetchDoctorSchedule(newDoctorId);
  } else {
    data.value = null
  }
})

// Инициализация при монтировании
onMounted(async() => {
    setTodayDate();
    fetchClinics();
    fetchDoctors();
  if (data.value && availableDates.value.length > 0) {
    selectNextAvailableDate()
  }
})
</script>

<style>
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
}

.time-header {
  display: flex;
  background: #f8f9fa;
  border-bottom: 1px solid #e0e0e0;
}

.hour-header {
  padding: 8px 4px;
  text-align: center;
  font-size: 12px;
  font-weight: 500;
  color: #495057;
  border-right: 1px solid #e0e0e0;
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

/* Адаптивность */
@media (max-width: 960px) {
  .compact-time-table {
    font-size: 11px;
  }
  
  .hour-header {
    padding: 6px 2px;
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

#__nuxt .main {
  margin-top: 0.7rem;
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
</style>
