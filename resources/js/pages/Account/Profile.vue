<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref } from 'vue'

import AccountShell from '@/components/Account/AccountShell.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'

defineOptions({
  layout: (h: any, page: any) => {
    const role = String(page?.props?.auth?.user?.role ?? '')
    const layout = role === 'admin' ? AdminDashboard : role === 'coach' ? CoachDashboard : StudentAthleteDashboard
    return h(layout, [page])
  },
})

const DEFAULT_AVATAR_URL = '/images/default-avatar.svg'

const props = defineProps<{
  profile: {
    admin: {
      role: string
      status: string
      capabilities: string[]
    } | null
    student: {
      student_id_number: string | null
      first_name: string | null
      middle_name: string | null
      last_name: string | null
      date_of_birth: string | null
      gender: string | null
      phone_number: string | null
      home_address: string | null
      course_or_strand: string | null
      education_level: string | null
      current_grade_level: string | null
      student_status: string | null
      emergency_contact_name: string | null
      emergency_contact_relationship: string | null
      emergency_contact_phone: string | null
      height: string | number | null
      weight: string | number | null
    } | null
    coach: {
      phone_number: string | null
      home_address: string | null
      date_of_birth: string | null
      gender: string | null
    } | null
  }
}>()

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const role = computed(() => String(user.value?.role ?? ''))

const form = useForm({
  name: String(user.value?.name ?? ''),
  avatar: null as File | null,
  phone_number: props.profile.student?.phone_number ?? props.profile.coach?.phone_number ?? '',
  home_address: props.profile.student?.home_address ?? props.profile.coach?.home_address ?? '',
  emergency_contact_name: props.profile.student?.emergency_contact_name ?? '',
  emergency_contact_relationship: props.profile.student?.emergency_contact_relationship ?? '',
  emergency_contact_phone: props.profile.student?.emergency_contact_phone ?? '',
  date_of_birth: props.profile.coach?.date_of_birth ?? '',
  gender: props.profile.coach?.gender ?? '',
})

const emergencyRelationshipOptions = ['Parent', 'Guardian', 'Sibling', 'Grandparent', 'Relative', 'Spouse', 'Other']

const saved = ref(false)
const requestUpdateOpen = ref(false)
const avatarPreview = ref<string | null>(null)
const avatarInput = ref<HTMLInputElement | null>(null)

const cropModalOpen = ref(false)
const cropSourceUrl = ref<string | null>(null)
const cropImageEl = ref<HTMLImageElement | null>(null)
const cropFrameEl = ref<HTMLDivElement | null>(null)
const cropScale = ref(1)
const cropMinScale = ref(1)
const cropX = ref(0)
const cropY = ref(0)
const cropError = ref('')

let dragActive = false
let dragStartX = 0
let dragStartY = 0
let dragOriginX = 0
let dragOriginY = 0

const maxCropScale = computed(() => Math.max(cropMinScale.value * 4, cropMinScale.value + 1))

const cropImageStyle = computed(() => ({
  transform: `translate(calc(-50% + ${cropX.value}px), calc(-50% + ${cropY.value}px)) scale(${cropScale.value})`,
}))

const avatarUrl = computed(() => {
  if (avatarPreview.value) return avatarPreview.value
  const path = String(user.value?.avatar ?? '')
  if (!path) return DEFAULT_AVATAR_URL
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  if (path.startsWith('/storage/')) return path
  return `/storage/${path}`
})

function displayValue(value: string | number | null | undefined) {
  if (value === null || value === undefined) return 'N/A'
  const text = String(value).trim()
  return text.length ? text : 'N/A'
}

const studentDetails = computed(() => {
  const student = props.profile.student
  if (!student) return []
  const recordName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ').trim()
  const yearOrGrade = student.current_grade_level
  return [
    { label: 'Student Record Name', value: recordName || null },
    { label: 'Student ID', value: student.student_id_number },
    { label: 'Date of Birth', value: student.date_of_birth },
    { label: 'Gender', value: student.gender },
    { label: 'Course/Strand', value: student.course_or_strand },
    { label: 'Education Level', value: student.education_level },
    { label: 'Current Grade Level', value: yearOrGrade },
    { label: 'Status', value: student.student_status },
    { label: 'Height', value: student.height },
    { label: 'Weight', value: student.weight },
  ]
})


function revokeUrl(value: string | null) {
  if (value?.startsWith('blob:')) {
    URL.revokeObjectURL(value)
  }
}

function triggerAvatarPicker() {
  avatarInput.value?.click()
}

function resetCropState() {
  cropScale.value = 1
  cropMinScale.value = 1
  cropX.value = 0
  cropY.value = 0
  cropError.value = ''
}

function closeCropModal() {
  cropModalOpen.value = false
  cropImageEl.value = null
  revokeUrl(cropSourceUrl.value)
  cropSourceUrl.value = null
  resetCropState()
  removeDragListeners()
}

function onAvatarChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  if (!file) return

  if (!file.type.startsWith('image/')) {
    form.setError('avatar', 'Please select a valid image file.')
    input.value = ''
    return
  }

  form.clearErrors('avatar')
  resetCropState()
  revokeUrl(cropSourceUrl.value)
  cropSourceUrl.value = URL.createObjectURL(file)
  cropModalOpen.value = true
  input.value = ''
}

function onCropImageLoad() {
  if (!cropImageEl.value || !cropFrameEl.value) return

  const image = cropImageEl.value
  const frame = cropFrameEl.value
  const frameSize = Math.max(frame.clientWidth, 1)
  const width = Math.max(image.naturalWidth, 1)
  const height = Math.max(image.naturalHeight, 1)
  const minScale = Math.max(frameSize / width, frameSize / height)

  cropMinScale.value = minScale
  cropScale.value = minScale
  cropX.value = 0
  cropY.value = 0
}

function beginCropDrag(event: PointerEvent) {
  if (!cropModalOpen.value) return

  dragActive = true
  dragStartX = event.clientX
  dragStartY = event.clientY
  dragOriginX = cropX.value
  dragOriginY = cropY.value

  const target = event.currentTarget as HTMLElement | null
  target?.setPointerCapture?.(event.pointerId)
  window.addEventListener('pointermove', onCropDrag)
  window.addEventListener('pointerup', endCropDrag)
  window.addEventListener('pointercancel', endCropDrag)
}

function onCropDrag(event: PointerEvent) {
  if (!dragActive) return
  cropX.value = dragOriginX + (event.clientX - dragStartX)
  cropY.value = dragOriginY + (event.clientY - dragStartY)
}

function endCropDrag() {
  dragActive = false
  removeDragListeners()
}

function removeDragListeners() {
  window.removeEventListener('pointermove', onCropDrag)
  window.removeEventListener('pointerup', endCropDrag)
  window.removeEventListener('pointercancel', endCropDrag)
}

function adjustCropZoom(delta: number) {
  const nextScale = cropScale.value + delta
  cropScale.value = Math.min(maxCropScale.value, Math.max(cropMinScale.value, nextScale))
}

function onCropWheel(event: WheelEvent) {
  const zoomDelta = event.deltaY < 0 ? 0.05 : -0.05
  adjustCropZoom(zoomDelta)
}

async function applyCroppedAvatar() {
  if (!cropImageEl.value || !cropFrameEl.value) {
    cropError.value = 'Unable to prepare image crop.'
    return
  }

  const image = cropImageEl.value
  const frameSize = Math.max(cropFrameEl.value.clientWidth, 1)
  const outputSize = 512
  const ratio = outputSize / frameSize

  const canvas = document.createElement('canvas')
  canvas.width = outputSize
  canvas.height = outputSize

  const ctx = canvas.getContext('2d')
  if (!ctx) {
    cropError.value = 'Canvas is not available in this browser.'
    return
  }

  ctx.clearRect(0, 0, outputSize, outputSize)
  ctx.save()
  ctx.translate(outputSize / 2 + cropX.value * ratio, outputSize / 2 + cropY.value * ratio)
  ctx.scale(cropScale.value * ratio, cropScale.value * ratio)
  ctx.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2)
  ctx.restore()

  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', 0.92)
  })

  if (!blob) {
    cropError.value = 'Failed to create cropped image.'
    return
  }

  const file = new File([blob], `avatar-${Date.now()}.jpg`, { type: 'image/jpeg' })

  revokeUrl(avatarPreview.value)
  avatarPreview.value = URL.createObjectURL(file)
  form.avatar = file
  form.clearErrors('avatar')

  closeCropModal()
}

function submit() {
  saved.value = false
  form.put('/account/profile', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      saved.value = true
      setTimeout(() => {
        saved.value = false
      }, 2200)
    },
  })
}

onBeforeUnmount(() => {
  revokeUrl(avatarPreview.value)
  revokeUrl(cropSourceUrl.value)
  removeDragListeners()
})
</script>

<template>
  <Head title="My Profile" />

  <AccountShell active="profile">
    <section class="rounded-2xl border border-[#034485]/45 bg-gradient-to-br from-white via-white to-slate-50/60 p-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Profile</h1>
          <p class="text-sm text-slate-600">Manage your account identity and role-specific information.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
            {{ role === 'admin' ? 'Administrator' : role }}
          </span>
        </div>
      </div>
    </section>

    <form @submit.prevent="submit" class="space-y-4">
          <section class="rounded-2xl border border-[#034485]/45 bg-white p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
              <img :src="avatarUrl" alt="Avatar" class="h-20 w-20 rounded-full object-cover border border-[#034485]/45" />
              <div class="flex-1">
                <p class="text-sm font-medium text-slate-700">Profile Photo</p>
                <p class="text-xs text-slate-500">JPG, PNG, or WebP up to 2MB. Crop works on mobile and desktop.</p>
                <input ref="avatarInput" type="file" accept="image/png,image/jpeg,image/webp" @change="onAvatarChange" class="hidden" />
                <button type="button" class="mt-2 rounded-md border border-[#034485]/45 px-3 py-1.5 text-sm hover:bg-slate-50" @click="triggerAvatarPicker">
                  Choose Photo
                </button>
              </div>
            </div>
            <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-600">{{ form.errors.avatar }}</p>
          </section>

          <section class="rounded-2xl border border-[#034485]/45 bg-white p-5 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full Name</label>
              <input v-model="form.name" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" required />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <template v-if="role !== 'admin'">
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</label>
                <input v-model="form.phone_number" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
                <p v-if="form.errors.phone_number" class="mt-1 text-xs text-red-600">{{ form.errors.phone_number }}</p>
              </div>
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                <input v-model="form.home_address" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
                <p v-if="form.errors.home_address" class="mt-1 text-xs text-red-600">{{ form.errors.home_address }}</p>
              </div>
            </template>

            <template v-if="role === 'student' || role === 'student-athlete'">
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Contact Name</label>
                <input v-model="form.emergency_contact_name" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
                <p v-if="form.errors.emergency_contact_name" class="mt-1 text-xs text-red-600">{{ form.errors.emergency_contact_name }}</p>
              </div>
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Relationship</label>
                <select v-model="form.emergency_contact_relationship" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm">
                  <option value="">Select relationship</option>
                  <option v-for="option in emergencyRelationshipOptions" :key="option" :value="option">{{ option }}</option>
                </select>
                <p v-if="form.errors.emergency_contact_relationship" class="mt-1 text-xs text-red-600">{{ form.errors.emergency_contact_relationship }}</p>
              </div>
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Emergency Contact Phone</label>
                <input v-model="form.emergency_contact_phone" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
                <p v-if="form.errors.emergency_contact_phone" class="mt-1 text-xs text-red-600">{{ form.errors.emergency_contact_phone }}</p>
              </div>
            </template>

            <template v-if="role === 'coach'">
              <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of Birth</label>
            <input v-model="form.date_of_birth" type="date" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
            <p v-if="form.errors.date_of_birth" class="mt-1 text-xs text-red-600">{{ form.errors.date_of_birth }}</p>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</label>
            <input v-model="form.gender" type="text" class="mt-1 w-full rounded-xl border border-[#034485]/45 px-3 py-2 text-sm" />
            <p v-if="form.errors.gender" class="mt-1 text-xs text-red-600">{{ form.errors.gender }}</p>
          </div>
        </template>
      </section>

      <section v-if="(role === 'student' || role === 'student-athlete') && profile.student" class="rounded-2xl border border-[#034485]/45 bg-white p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-900">Student Details</h2>
            <p class="text-xs text-slate-500">These details are pulled from your student record. Contact an admin to update.</p>
          </div>
          <button
            type="button"
            class="mt-2 inline-flex items-center justify-center rounded-md border border-[#034485]/45 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-[#034485]/70 sm:mt-0"
            @click="requestUpdateOpen = !requestUpdateOpen"
          >
            Request Update
          </button>
        </div>
        <div v-if="requestUpdateOpen" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          To update student record details, contact your athletics admin or registrar and include your student ID number.
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in studentDetails" :key="item.label" class="rounded-xl border border-[#034485]/30 bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ item.label }}</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ displayValue(item.value) }}</p>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="rounded-lg bg-[#1f2937] px-4 py-2 text-white font-semibold hover:bg-[#334155] transition" :disabled="form.processing">
          {{ form.processing ? 'Saving...' : 'Save Profile' }}
        </button>
        <p v-if="saved" class="text-sm text-green-700">Profile updated.</p>
      </div>
    </form>

    <div v-if="cropModalOpen" class="crop-overlay">
      <div class="crop-modal">
        <div class="crop-header">
          <h3 class="text-base font-semibold text-slate-900">Crop Profile Photo</h3>
          <p class="text-xs text-slate-500">Drag to reposition. Use zoom for better framing.</p>
        </div>

        <div ref="cropFrameEl" class="crop-frame" @pointerdown.prevent="beginCropDrag" @wheel.prevent="onCropWheel">
          <img v-if="cropSourceUrl" ref="cropImageEl" :src="cropSourceUrl" alt="Crop preview" class="crop-image" :style="cropImageStyle" @load="onCropImageLoad" />
          <div class="crop-frame-ring" />
        </div>

        <div class="mt-3 space-y-2">
          <div class="flex items-center gap-2">
            <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="adjustCropZoom(-0.1)">-</button>
            <input
              :value="cropScale"
              type="range"
              class="w-full"
              :min="cropMinScale"
              :max="maxCropScale"
              step="0.01"
              @input="cropScale = Number(($event.target as HTMLInputElement).value)"
            />
            <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs" @click="adjustCropZoom(0.1)">+</button>
          </div>
          <p v-if="cropError" class="text-xs text-red-600">{{ cropError }}</p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="closeCropModal">
            Cancel
          </button>
          <button type="button" class="rounded-md bg-[#1f2937] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#334155]" @click="applyCroppedAvatar">
            Use Cropped Photo
          </button>
        </div>
      </div>
    </div>
  </AccountShell>
</template>

<style scoped>
.crop-overlay {
  position: fixed;
  inset: 0;
  z-index: 70;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.7);
  padding: 1rem;
}

.crop-modal {
  width: min(96vw, 480px);
  max-height: 94vh;
  overflow: auto;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  padding: 1rem;
  box-shadow: 0 14px 40px rgba(15, 23, 42, 0.24);
}

.crop-header {
  margin-bottom: 0.75rem;
}

.crop-frame {
  position: relative;
  width: min(80vw, 320px);
  height: min(80vw, 320px);
  max-width: 320px;
  max-height: 320px;
  margin: 0 auto;
  border-radius: 999px;
  overflow: hidden;
  background: #e2e8f0;
  touch-action: none;
  user-select: none;
  cursor: grab;
}

.crop-frame:active {
  cursor: grabbing;
}

.crop-image {
  position: absolute;
  top: 50%;
  left: 50%;
  transform-origin: center center;
  max-width: none;
  max-height: none;
  will-change: transform;
}

.crop-frame-ring {
  position: absolute;
  inset: 0;
  border:  1px solid rgba(255, 255, 255, 0.95);
  border-radius: 999px;
  pointer-events: none;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.2);
}

</style>
