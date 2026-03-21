<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useInertiaLoading } from '@/composables/useInertiaLoading';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';

type Step = 1 | 2 | 3;
type EducationLevel = 'Senior High' | 'College';
type AcademicDocumentType = 'tor' | 'grade_report' | 'other';
type ClearanceStatus = 'fit' | 'fit_with_restrictions' | 'not_fit' | 'expired';

const draftKey = 'ac-vmis-student-registration-draft-v2';
const acceptedDocsText = 'Accepted file types: PDF, JPG, JPEG, PNG (max 5MB each)';

const step = ref<Step>(1);
const isSubmitting = ref(false);
const isStepTransitioning = ref(false);
const checkingStudentId = ref(false);
const studentIdAvailable = ref<boolean | null>(null);
const uploadProgress = ref(0);
let studentIdDebounce: ReturnType<typeof setTimeout> | null = null;
let stepTransitionTimer: ReturnType<typeof setTimeout> | null = null;
const { isLoading } = useInertiaLoading();

const modal = reactive({
    open: false,
    title: '',
    message: '',
});

const form = reactive({
    email: '',
    password: '',
    password_confirmation: '',
    avatar: null as File | null,

    student_id_number: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    date_of_birth: '',
    gender: '' as '' | 'Male' | 'Female' | 'Other',
    phone_number: '',
    home_address: '',
    education_level: 'Senior High' as EducationLevel,
    year_level: '11',
    student_status: 'Enrolled',
    emergency_contact_name: '',
    emergency_contact_relationship: '',
    emergency_contact_phone: '',

    height_unit: 'cm' as 'cm' | 'ft',
    height_cm: '',
    height_ft: '',
    height_in: '',
    weight_kg: '',

    clearance_date: '',
    valid_until: '',
    physician_name: '',
    conditions: '',
    allergies: '',
    restrictions: '',
    clearance_status: 'fit' as ClearanceStatus,
    medical_certificate: null as File | null,

    academic_document_type: 'tor' as AcademicDocumentType,
    academic_document_file: null as File | null,
    academic_document_notes: '',
});

const fieldErrors = reactive<Record<string, string>>({});

const yearLevelOptions = computed(() => {
    if (form.education_level === 'Senior High') {
        return ['11', '12'];
    }

    return ['1', '2', '3', '4'];
});

const fullNamePreview = computed(() => {
    return [form.first_name, form.last_name].filter(Boolean).join(' ').trim();
});

const selectedFileNames = computed(() => ({
    avatar: form.avatar?.name ?? 'No file selected',
    medical: form.medical_certificate?.name ?? 'No file selected',
    academic: form.academic_document_file?.name ?? 'No file selected',
}));
const avatarPreviewUrl = ref<string | null>(null);
const cropModalOpen = ref(false);
const cropSourceUrl = ref<string | null>(null);
const cropImageEl = ref<HTMLImageElement | null>(null);
const cropFrameEl = ref<HTMLDivElement | null>(null);
const cropScale = ref(1);
const cropMinScale = ref(1);
const cropX = ref(0);
const cropY = ref(0);
const cropError = ref('');
const showPassword = ref(false);
const showPasswordConfirm = ref(false);

let cropDragActive = false;
let cropDragStartX = 0;
let cropDragStartY = 0;
let cropDragOriginX = 0;
let cropDragOriginY = 0;

const maxCropScale = computed(() => Math.max(cropMinScale.value * 4, cropMinScale.value + 1));
const cropImageStyle = computed(() => ({
    transform: `translate(calc(-50% + ${cropX.value}px), calc(-50% + ${cropY.value}px)) scale(${cropScale.value})`,
}));

function toWelcome() {
    router.visit('/');
}

function toLogin() {
    router.visit('/Login');
}

function openModal(title: string, message: string) {
    modal.title = title;
    modal.message = message;
    modal.open = true;
}

function closeModal() {
    modal.open = false;
}

function validateEmail(email: string) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateStudentIdFormat(value: string) {
    return /^[A-Za-z0-9-]{6,20}$/.test(value);
}

function resolveHeightCm(): number | null {
    if (form.height_unit === 'cm') {
        const value = Number(form.height_cm);
        if (!Number.isFinite(value)) {
            return null;
        }

        return value;
    }

    const ft = Number(form.height_ft || 0);
    const inches = Number(form.height_in || 0);
    if (!Number.isFinite(ft) || !Number.isFinite(inches)) {
        return null;
    }

    const totalInches = ft * 12 + inches;
    if (totalInches <= 0) {
        return null;
    }

    return Number((totalInches * 2.54).toFixed(2));
}

function setFieldError(field: string, message: string) {
    fieldErrors[field] = message;
}

function clearFieldError(field: string) {
    fieldErrors[field] = '';
}

function validateField(field: string): boolean {
    const value = (form as Record<string, unknown>)[field];

    if (field === 'email') {
        if (!String(value || '').trim()) {
            setFieldError(field, 'Email is required.');
            return false;
        }
        if (!validateEmail(String(value))) {
            setFieldError(field, 'Enter a valid email address.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'password') {
        if (!String(value || '').trim()) {
            setFieldError(field, 'Password is required.');
            return false;
        }
        if (String(value).length < 6) {
            setFieldError(field, 'Password must be at least 6 characters.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'password_confirmation') {
        if (!String(value || '').trim()) {
            setFieldError(field, 'Confirm your password.');
            return false;
        }
        if (form.password !== form.password_confirmation) {
            setFieldError(field, 'Passwords do not match.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'student_id_number') {
        if (!String(value || '').trim()) {
            setFieldError(field, 'Student ID is required.');
            return false;
        }
        if (!validateStudentIdFormat(String(value))) {
            setFieldError(field, 'Use 6-20 characters (letters, numbers, hyphen).');
            return false;
        }
        if (studentIdAvailable.value === false) {
            setFieldError(field, 'Student ID already exists.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (['first_name', 'last_name', 'date_of_birth', 'gender', 'phone_number', 'education_level', 'year_level'].includes(field)) {
        if (!String(value || '').trim()) {
            setFieldError(field, 'This field is required.');
            return false;
        }

        clearFieldError(field);
        return true;
    }

    if (field === 'weight_kg') {
        const weightRaw = String(form.weight_kg ?? '').trim();
        const weight = Number(weightRaw);
        if (!weightRaw) {
            setFieldError(field, 'Weight (kg) is required.');
            return false;
        }
        if (!Number.isFinite(weight) || weight < 20 || weight > 300) {
            setFieldError(field, 'Weight must be between 20 and 300 kg.');
            return false;
        }

        clearFieldError(field);
        return true;
    }

    if (field === 'height') {
        const height = resolveHeightCm();
        if (height === null) {
            setFieldError('height', 'Height is required.');
            return false;
        }
        if (height < 50 || height > 260) {
            setFieldError('height', 'Height must be between 50 and 260 cm.');
            return false;
        }

        clearFieldError('height');
        return true;
    }

    if (field === 'clearance_date') {
        if (!form.clearance_date) {
            setFieldError(field, 'Medical clearance date is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'physician_name') {
        if (!form.physician_name.trim()) {
            setFieldError(field, 'Physician name is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'medical_certificate') {
        if (!form.medical_certificate) {
            setFieldError(field, 'Medical certificate is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'academic_document_file') {
        if (!form.academic_document_file) {
            setFieldError(field, 'Academic document is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    return true;
}

watch(
    () => form.education_level,
    () => {
        const options = yearLevelOptions.value;
        if (!options.includes(form.year_level)) {
            form.year_level = options[0] ?? '';
        }
        validateField('education_level');
        validateField('year_level');
    },
);

watch(
    () => form.student_id_number,
    (value) => {
        studentIdAvailable.value = null;

        if (!value || !validateStudentIdFormat(value)) {
            validateField('student_id_number');
            return;
        }

        clearFieldError('student_id_number');

        if (studentIdDebounce) {
            clearTimeout(studentIdDebounce);
        }

        studentIdDebounce = setTimeout(async () => {
            checkingStudentId.value = true;
            try {
                const params = new URLSearchParams({ student_id_number: value });
                const response = await fetch(`/RegisterStudent-AthleteData/check-student-id?${params.toString()}`);
                if (!response.ok) {
                    throw new Error('Unable to validate student ID right now.');
                }

                const payload = await response.json();
                studentIdAvailable.value = Boolean(payload.available);

                if (!studentIdAvailable.value) {
                    setFieldError('student_id_number', 'Student ID already exists.');
                }
            } catch {
                setFieldError('student_id_number', 'Unable to validate Student ID. Try again.');
            } finally {
                checkingStudentId.value = false;
            }
        }, 450);
    },
);

watch(
    () => [form.email, form.password, form.password_confirmation, form.first_name, form.last_name],
    () => {
        validateField('email');
        validateField('password');
        validateField('password_confirmation');
        if (form.first_name) {
            validateField('first_name');
        }
        if (form.last_name) {
            validateField('last_name');
        }
    },
);

watch(
    () => form.avatar,
    (file) => {
        revokeBlobUrl(avatarPreviewUrl.value);
        avatarPreviewUrl.value = file ? URL.createObjectURL(file) : null;
    },
);

function validateStep(currentStep: Step): boolean {
    const checks: Record<Step, string[]> = {
        1: ['email', 'password', 'password_confirmation', 'student_id_number'],
        2: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone_number', 'education_level', 'year_level', 'height', 'weight_kg'],
        3: ['clearance_date', 'physician_name', 'medical_certificate', 'academic_document_file'],
    };

    return checks[currentStep].every((field) => validateField(field));
}

function nextStep() {
    if (!validateStep(step.value)) {
        openModal('Incomplete Step', 'Please fix the highlighted fields before continuing.');
        return;
    }

    if (step.value < 3) {
        isStepTransitioning.value = true;
        step.value = (step.value + 1) as Step;
        if (stepTransitionTimer) clearTimeout(stepTransitionTimer);
        stepTransitionTimer = setTimeout(() => {
            isStepTransitioning.value = false;
        }, 260);
    }
}

function previousStep() {
    if (step.value > 1) {
        isStepTransitioning.value = true;
        step.value = (step.value - 1) as Step;
        if (stepTransitionTimer) clearTimeout(stepTransitionTimer);
        stepTransitionTimer = setTimeout(() => {
            isStepTransitioning.value = false;
        }, 260);
    }
}

function revokeBlobUrl(value: string | null) {
    if (value?.startsWith('blob:')) {
        URL.revokeObjectURL(value);
    }
}

function resetCropState() {
    cropScale.value = 1;
    cropMinScale.value = 1;
    cropX.value = 0;
    cropY.value = 0;
    cropError.value = '';
}

function removeCropDragListeners() {
    window.removeEventListener('pointermove', onCropDrag);
    window.removeEventListener('pointerup', endCropDrag);
    window.removeEventListener('pointercancel', endCropDrag);
}

function closeCropModal() {
    cropModalOpen.value = false;
    cropImageEl.value = null;
    revokeBlobUrl(cropSourceUrl.value);
    cropSourceUrl.value = null;
    resetCropState();
    removeCropDragListeners();
}

function onCropImageLoad() {
    if (!cropImageEl.value || !cropFrameEl.value) return;

    const image = cropImageEl.value;
    const frame = cropFrameEl.value;
    const frameSize = Math.max(frame.clientWidth, 1);
    const width = Math.max(image.naturalWidth, 1);
    const height = Math.max(image.naturalHeight, 1);
    const minScale = Math.max(frameSize / width, frameSize / height);

    cropMinScale.value = minScale;
    cropScale.value = minScale;
    cropX.value = 0;
    cropY.value = 0;
}

function beginCropDrag(event: PointerEvent) {
    if (!cropModalOpen.value) return;

    cropDragActive = true;
    cropDragStartX = event.clientX;
    cropDragStartY = event.clientY;
    cropDragOriginX = cropX.value;
    cropDragOriginY = cropY.value;

    const target = event.currentTarget as HTMLElement | null;
    target?.setPointerCapture?.(event.pointerId);

    window.addEventListener('pointermove', onCropDrag);
    window.addEventListener('pointerup', endCropDrag);
    window.addEventListener('pointercancel', endCropDrag);
}

function onCropDrag(event: PointerEvent) {
    if (!cropDragActive) return;

    cropX.value = cropDragOriginX + (event.clientX - cropDragStartX);
    cropY.value = cropDragOriginY + (event.clientY - cropDragStartY);
}

function endCropDrag() {
    cropDragActive = false;
    removeCropDragListeners();
}

function adjustCropZoom(delta: number) {
    const nextScale = cropScale.value + delta;
    cropScale.value = Math.min(maxCropScale.value, Math.max(cropMinScale.value, nextScale));
}

function onCropWheel(event: WheelEvent) {
    const zoomDelta = event.deltaY < 0 ? 0.05 : -0.05;
    adjustCropZoom(zoomDelta);
}

async function applyCroppedAvatar() {
    if (!cropImageEl.value || !cropFrameEl.value) {
        cropError.value = 'Unable to prepare image crop.';
        return;
    }

    const image = cropImageEl.value;
    const frameSize = Math.max(cropFrameEl.value.clientWidth, 1);
    const outputSize = 512;
    const ratio = outputSize / frameSize;

    const canvas = document.createElement('canvas');
    canvas.width = outputSize;
    canvas.height = outputSize;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        cropError.value = 'Canvas is not available in this browser.';
        return;
    }

    ctx.clearRect(0, 0, outputSize, outputSize);
    ctx.save();
    ctx.translate(outputSize / 2 + cropX.value * ratio, outputSize / 2 + cropY.value * ratio);
    ctx.scale(cropScale.value * ratio, cropScale.value * ratio);
    ctx.drawImage(image, -image.naturalWidth / 2, -image.naturalHeight / 2);
    ctx.restore();

    const blob = await new Promise<Blob | null>((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', 0.92);
    });

    if (!blob) {
        cropError.value = 'Failed to create cropped image.';
        return;
    }

    form.avatar = new File([blob], `student-avatar-${Date.now()}.jpg`, { type: 'image/jpeg' });
    fieldErrors.avatar = '';
    closeCropModal();
}

function setFile(field: 'avatar' | 'medical_certificate' | 'academic_document_file', event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (field === 'avatar') {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            setFieldError('avatar', 'Please select a valid image file.');
            input.value = '';
            return;
        }

        clearFieldError('avatar');
        resetCropState();
        revokeBlobUrl(cropSourceUrl.value);
        cropSourceUrl.value = URL.createObjectURL(file);
        cropModalOpen.value = true;
        input.value = '';
        return;
    }

    form[field] = file;

    if (field === 'medical_certificate') {
        validateField('medical_certificate');
    }

    if (field === 'academic_document_file') {
        validateField('academic_document_file');
    }
}

function saveDraft() {
    const payload = {
        ...form,
        avatar: null,
        medical_certificate: null,
        academic_document_file: null,
    };

    localStorage.setItem(draftKey, JSON.stringify(payload));
    openModal('Draft Saved', 'Your registration draft was saved on this browser. You can continue anytime.');
}

function restoreDraft() {
    const raw = localStorage.getItem(draftKey);
    if (!raw) {
        return;
    }

    try {
        const parsed = JSON.parse(raw) as Partial<typeof form>;
        Object.assign(form, parsed);
        openModal('Draft Restored', 'Your previous registration draft has been loaded.');
    } catch {
        localStorage.removeItem(draftKey);
    }
}

function clearDraft() {
    localStorage.removeItem(draftKey);
}

function submit() {
    if (!validateStep(3) || !validateStep(2) || !validateStep(1)) {
        openModal('Cannot Submit', 'Please complete all required fields first.');
        return;
    }

    const height = resolveHeightCm();
    if (!height) {
        setFieldError('height', 'Height is required.');
        return;
    }

    const formData = new FormData();
    formData.append('email', form.email);
    formData.append('role', 'student-athlete');
    formData.append('password', form.password);
    formData.append('password_confirmation', form.password_confirmation);

    if (form.avatar) {
        formData.append('avatar', form.avatar);
    }

    formData.append('student_id_number', form.student_id_number);
    formData.append('first_name', form.first_name);
    formData.append('middle_name', form.middle_name);
    formData.append('last_name', form.last_name);
    formData.append('date_of_birth', form.date_of_birth);
    formData.append('gender', form.gender);
    formData.append('phone_number', form.phone_number);
    formData.append('home_address', form.home_address);
    formData.append('education_level', form.education_level);
    formData.append('year_level', form.year_level);
    formData.append('student_status', 'Enrolled');

    formData.append('height', String(height));
    formData.append('weight', form.weight_kg);

    formData.append('emergency_contact_name', form.emergency_contact_name);
    formData.append('emergency_contact_relationship', form.emergency_contact_relationship);
    formData.append('emergency_contact_phone', form.emergency_contact_phone);

    formData.append('clearance_date', form.clearance_date);
    formData.append('valid_until', form.valid_until);
    formData.append('physician_name', form.physician_name);
    formData.append('conditions', form.conditions);
    formData.append('allergies', form.allergies);
    formData.append('restrictions', form.restrictions);
    formData.append('clearance_status', form.clearance_status);
    if (form.medical_certificate) {
        formData.append('medical_certificate', form.medical_certificate);
    }

    formData.append('academic_document_type', form.academic_document_type);
    if (form.academic_document_file) {
        formData.append('academic_document_file', form.academic_document_file);
    }
    formData.append('academic_document_notes', form.academic_document_notes);

    router.post('/RegisterStudent-AthleteData', formData, {
        forceFormData: true,
        onStart: () => {
            isSubmitting.value = true;
            uploadProgress.value = 0;
        },
        onProgress: (event) => {
            uploadProgress.value = Math.round(event?.percentage ?? 0);
        },
        onFinish: () => {
            isSubmitting.value = false;
            uploadProgress.value = 0;
        },
        onSuccess: () => {
            clearDraft();
            router.visit('/pending-approval');
        },
        onError: (errors) => {
            Object.keys(fieldErrors).forEach((key) => {
                fieldErrors[key] = '';
            });

            for (const [key, value] of Object.entries(errors)) {
                const message = Array.isArray(value) ? value[0] : value;
                if (typeof message === 'string') {
                    fieldErrors[key] = message;
                }
            }

            const studentIdError = fieldErrors.student_id_number;
            if (studentIdError) {
                openModal('Student ID Conflict', studentIdError);
                step.value = 1;
                return;
            }

            const generic = fieldErrors.error || 'Please review the form and try again.';
            openModal('Registration Error', generic);
        },
    });
}

onMounted(() => {
    restoreDraft();
});

onBeforeUnmount(() => {
    if (studentIdDebounce) {
        clearTimeout(studentIdDebounce);
    }
    if (stepTransitionTimer) {
        clearTimeout(stepTransitionTimer);
    }
    revokeBlobUrl(cropSourceUrl.value);
    revokeBlobUrl(avatarPreviewUrl.value);
    removeCropDragListeners();
});
</script>

<template>
    <Head title="Student-Athlete Registration" />

    <div class="register-page">
        <header class="register-header px-4 pb-4 pt-4 sm:px-6 sm:pt-6 lg:px-10">
            <div v-if="isLoading" class="top-loading" />
            <div class="register-nav mx-auto max-w-6xl">
                <div>
                    <p class="kicker">Asian College</p>
                    <p class="brand">Varsity Management Information System</p>
                </div>
                <button @click="toWelcome" class="btn-outline" :disabled="isLoading || isSubmitting">Back To Home</button>
            </div>
        </header>

        <main class="register-main px-4 py-8 sm:px-6 lg:px-10">
            <div class="mx-auto w-full max-w-4xl rounded-2xl border border-[rgba(15, 23, 42,0.35)] bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-center text-2xl font-bold text-[#1f2937]">Student-Athlete Registration</h1>
                <p class="mt-2 text-center text-sm text-slate-500">3-step signup. Required now: account, identity basics, and requirements.</p>

                <div class="mt-6 stepper">
                    <div class="step" :class="{ active: step >= 1 }"><span>1</span><small>Account</small></div>
                    <div class="step-line" :class="{ active: step >= 2 }"></div>
                    <div class="step" :class="{ active: step >= 2 }"><span>2</span><small>Basic Profile</small></div>
                    <div class="step-line" :class="{ active: step >= 3 }"></div>
                    <div class="step" :class="{ active: step >= 3 }"><span>3</span><small>Requirements</small></div>
                </div>

                <section v-if="isStepTransitioning" class="mt-7 grid gap-3">
                    <Skeleton class="h-12" />
                    <Skeleton class="h-12" />
                    <Skeleton class="h-12" />
                    <Skeleton class="h-20" />
                </section>

                <transition v-else name="step-fade" mode="out-in">
                    <section v-if="step === 1" key="step-1" class="mt-7 grid gap-4">
                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" class="field" placeholder="name@example.com" @blur="validateField('email')" />
                        <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Password</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="field pr-10"
                                    placeholder="At least 6 characters"
                                    @blur="validateField('password')"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg v-if="showPassword" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M3 3l18 18" />
                                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                        <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                                        <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</p>
                        </div>
                        <div>
                            <label class="label">Confirm Password</label>
                            <div class="relative">
                                <input
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirm ? 'text' : 'password'"
                                    class="field pr-10"
                                    placeholder="Repeat password"
                                    @blur="validateField('password_confirmation')"
                                />
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                                    :aria-label="showPasswordConfirm ? 'Hide password' : 'Show password'"
                                    @click="showPasswordConfirm = !showPasswordConfirm"
                                >
                                    <svg v-if="showPasswordConfirm" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M3 3l18 18" />
                                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                        <path d="M9.9 5.1A10.9 10.9 0 0 1 12 5c5 0 9.3 3.1 11 7-0.6 1.4-1.6 2.8-2.8 3.9" />
                                        <path d="M6.7 6.7C4.7 8.1 3.3 10 2 12c1.6 3.9 6 7 10 7 1.4 0 2.8-0.3 4.1-0.9" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="fieldErrors.password_confirmation" class="field-error">{{ fieldErrors.password_confirmation }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="label">Student ID Number</label>
                        <input
                            v-model="form.student_id_number"
                            type="text"
                            class="field"
                            placeholder="Example: 24-000123"
                            @blur="validateField('student_id_number')"
                        />
                        <div class="mt-1 text-xs">
                            <span v-if="checkingStudentId" class="text-[#1f2937]">Checking student ID...</span>
                            <span v-else-if="studentIdAvailable === true" class="text-emerald-600">Student ID is available.</span>
                            <span v-else-if="studentIdAvailable === false" class="text-red-600">Student ID is already in use.</span>
                        </div>
                        <p v-if="fieldErrors.student_id_number" class="field-error">{{ fieldErrors.student_id_number }}</p>
                    </div>

                    <div>
                        <label class="label">Avatar (Optional)</label>
                        <input type="file" class="field file-field" accept="image/*" @change="(event) => setFile('avatar', event)" />
                        <p class="mt-1 text-xs text-slate-500">Selected: {{ selectedFileNames.avatar }}</p>
                        <div v-if="avatarPreviewUrl" class="avatar-preview">
                            <img :src="avatarPreviewUrl" alt="Avatar preview" />
                            <div>
                                <p class="text-xs text-slate-500">Preview</p>
                                <p class="text-sm font-semibold text-slate-700">Avatar ready</p>
                            </div>
                        </div>
                        <p v-if="fieldErrors.avatar" class="field-error">{{ fieldErrors.avatar }}</p>
                    </div>
                    </section>

                    <section v-else-if="step === 2" key="step-2" class="mt-7 grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">First Name</label>
                            <input v-model="form.first_name" type="text" class="field" @blur="validateField('first_name')" />
                            <p v-if="fieldErrors.first_name" class="field-error">{{ fieldErrors.first_name }}</p>
                        </div>
                        <div>
                            <label class="label">Middle Name (Optional)</label>
                            <input v-model="form.middle_name" type="text" class="field" />
                        </div>
                        <div>
                            <label class="label">Last Name</label>
                            <input v-model="form.last_name" type="text" class="field" @blur="validateField('last_name')" />
                            <p v-if="fieldErrors.last_name" class="field-error">{{ fieldErrors.last_name }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">Full name preview: {{ fullNamePreview || 'Not set yet' }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Date of Birth</label>
                            <input v-model="form.date_of_birth" type="date" class="field" @blur="validateField('date_of_birth')" />
                            <p v-if="fieldErrors.date_of_birth" class="field-error">{{ fieldErrors.date_of_birth }}</p>
                        </div>
                        <div>
                            <label class="label">Gender</label>
                            <select v-model="form.gender" class="field" @blur="validateField('gender')">
                                <option value="" disabled>Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <p v-if="fieldErrors.gender" class="field-error">{{ fieldErrors.gender }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Phone Number</label>
                            <input v-model="form.phone_number" type="text" class="field" placeholder="09XXXXXXXXX" @blur="validateField('phone_number')" />
                            <p v-if="fieldErrors.phone_number" class="field-error">{{ fieldErrors.phone_number }}</p>
                        </div>
                        <div>
                            <label class="label">Home Address (Optional)</label>
                            <input v-model="form.home_address" type="text" class="field" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Education Level</label>
                            <select v-model="form.education_level" class="field" @blur="validateField('education_level')">
                                <option value="Senior High">Senior High</option>
                                <option value="College">College</option>
                            </select>
                            <p v-if="fieldErrors.education_level" class="field-error">{{ fieldErrors.education_level }}</p>
                        </div>
                        <div>
                            <label class="label">Year Level</label>
                            <select v-model="form.year_level" class="field" @blur="validateField('year_level')">
                                <option v-for="option in yearLevelOptions" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <p v-if="fieldErrors.year_level" class="field-error">{{ fieldErrors.year_level }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Height</label>
                            <div class="mb-2 flex gap-4 text-xs text-[#1f2937]">
                                <label class="inline-flex items-center gap-1">
                                    <input v-model="form.height_unit" type="radio" value="cm" />
                                    CM
                                </label>
                                <label class="inline-flex items-center gap-1">
                                    <input v-model="form.height_unit" type="radio" value="ft" />
                                    FT/IN
                                </label>
                            </div>

                            <div v-if="form.height_unit === 'cm'">
                                <input v-model="form.height_cm" type="number" class="field" placeholder="e.g. 170" @blur="validateField('height')" />
                            </div>
                            <div v-else class="grid grid-cols-2 gap-2">
                                <input v-model="form.height_ft" type="number" class="field" placeholder="ft" @blur="validateField('height')" />
                                <input v-model="form.height_in" type="number" class="field" placeholder="in" @blur="validateField('height')" />
                            </div>
                            <p v-if="fieldErrors.height" class="field-error">{{ fieldErrors.height }}</p>
                        </div>
                        <div>
                            <label class="label">Weight (kg)</label>
                            <input v-model="form.weight_kg" type="number" class="field" placeholder="e.g. 60" @blur="validateField('weight_kg')" />
                            <p v-if="fieldErrors.weight_kg" class="field-error">{{ fieldErrors.weight_kg }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-[#ef444433] bg-[#fff5f5] p-3">
                        <p class="mb-2 text-sm font-semibold text-[#b91c1c]">Emergency Contact (Compact Block)</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <input v-model="form.emergency_contact_name" type="text" class="field" placeholder="Contact Name" />
                            <input v-model="form.emergency_contact_relationship" type="text" class="field" placeholder="Relationship" />
                            <input v-model="form.emergency_contact_phone" type="text" class="field" placeholder="Phone" />
                        </div>
                    </div>
                    </section>

                    <section v-else key="step-3" class="mt-7 grid gap-4">
                    <h2 class="text-lg font-semibold text-[#1f2937]">Pre-Participation Health Clearance</h2>
                    <p class="text-xs text-slate-500">{{ acceptedDocsText }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Medical Clearance Date</label>
                            <input v-model="form.clearance_date" type="date" class="field" @blur="validateField('clearance_date')" />
                            <p v-if="fieldErrors.clearance_date" class="field-error">{{ fieldErrors.clearance_date }}</p>
                        </div>
                        <div>
                            <label class="label">Valid Until (Optional)</label>
                            <input v-model="form.valid_until" type="date" class="field" />
                        </div>
                    </div>

                    <div>
                        <label class="label">Physician Name</label>
                        <input v-model="form.physician_name" type="text" class="field" @blur="validateField('physician_name')" />
                        <p v-if="fieldErrors.physician_name" class="field-error">{{ fieldErrors.physician_name }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <textarea v-model="form.conditions" class="field min-h-21" placeholder="Conditions (Optional)"></textarea>
                        <textarea v-model="form.allergies" class="field min-h-21" placeholder="Allergies (Optional)"></textarea>
                        <textarea v-model="form.restrictions" class="field min-h-21" placeholder="Restrictions (Optional)"></textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Clearance Status</label>
                            <select v-model="form.clearance_status" class="field">
                                <option value="fit">Fit</option>
                                <option value="fit_with_restrictions">Fit with Restrictions</option>
                                <option value="not_fit">Not Fit</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Medical Certificate</label>
                            <input type="file" class="field file-field" accept=".pdf,image/*" @change="(event) => setFile('medical_certificate', event)" />
                            <p class="mt-1 text-xs text-slate-500">Selected: {{ selectedFileNames.medical }}</p>
                            <p v-if="fieldErrors.medical_certificate" class="field-error">{{ fieldErrors.medical_certificate }}</p>
                        </div>
                    </div>

                    <h2 class="mt-3 text-lg font-semibold text-[#1f2937]">Academic Standing Document</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Document Type</label>
                            <select v-model="form.academic_document_type" class="field">
                                <option value="tor">Transcript of Records (TOR)</option>
                                <option value="grade_report">Grade Report</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Academic Document</label>
                            <input type="file" class="field file-field" accept=".pdf,image/*" @change="(event) => setFile('academic_document_file', event)" />
                            <p class="mt-1 text-xs text-slate-500">Selected: {{ selectedFileNames.academic }}</p>
                            <p v-if="fieldErrors.academic_document_file" class="field-error">{{ fieldErrors.academic_document_file }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="label">Notes (Optional)</label>
                        <textarea v-model="form.academic_document_notes" class="field min-h-21" placeholder="Additional context"></textarea>
                    </div>

                    <button type="button" class="btn-outline w-fit" @click="saveDraft">Save As Draft</button>
                    </section>
                </transition>

                <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-[#e2e8f0] pt-4">
                    <button type="button" class="btn-outline" :disabled="step === 1 || isSubmitting" @click="previousStep">Previous</button>

                    <div class="flex items-center gap-2">
                        <button v-if="step < 3" type="button" class="btn-fill" :disabled="isSubmitting" @click="nextStep">Continue</button>
                        <button v-else type="button" class="btn-fill" :disabled="isSubmitting" @click="submit">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                                {{ isSubmitting ? 'Submitting...' : 'Create Account' }}
                            </span>
                        </button>
                    </div>
                </div>

                <div v-if="isSubmitting" class="mt-4 space-y-1.5">
                    <div class="flex items-center justify-between text-xs text-[#1f2937]">
                        <span>Uploading requirements...</span>
                        <span>{{ uploadProgress }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-[#e2e8f0] overflow-hidden">
                        <div class="h-full bg-[#1f2937] transition-all duration-150" :style="{ width: `${uploadProgress}%` }" />
                    </div>
                </div>
            </div>
        </main>

        <footer class="site-footer px-4 pb-5 sm:px-6 lg:px-10">
            <div class="footer-shell mx-auto max-w-6xl">
                <div class="footer-grid">
                    <section class="footer-col footer-col-brand">
                        <p class="footer-brand">Asian College Varsity Management Information System</p>
                        <p class="footer-copy">
                            Complete your student-athlete registration and submit the required health and academic records.
                        </p>
                    </section>
                    <nav class="footer-col" aria-label="Access Links">
                        <p class="footer-heading">Access</p>
                        <div class="footer-link-list">
                            <button @click="toLogin" class="footer-link footer-link-btn">Login</button>
                            <button @click="toWelcome" class="footer-link footer-link-btn">Home</button>
                        </div>
                    </nav>
                </div>
            </div>
        </footer>

        <div v-if="modal.open" class="dialog-overlay" @click.self="closeModal">
            <div class="dialog-card">
                <div class="dialog-icon">!</div>
                <h3 class="dialog-title">{{ modal.title }}</h3>
                <p class="dialog-message">{{ modal.message }}</p>
                <button type="button" class="btn-fill mt-2" @click="closeModal">Close</button>
            </div>
        </div>

        <div v-if="cropModalOpen" class="crop-overlay">
            <div class="crop-modal">
                <div class="crop-header">
                    <h3 class="text-base font-semibold text-slate-900">Crop Avatar</h3>
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
                    <button type="button" class="btn-outline" @click="closeCropModal">Cancel</button>
                    <button type="button" class="btn-fill" @click="applyCroppedAvatar">Use Cropped Photo</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.register-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #1f2937;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    font-size: 1rem;
    line-height: 1.6;
}

.register-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(2px);
    border-bottom:  1px solid rgba(15, 23, 42, 0.56);
}

.register-nav {
    border:  1px solid rgba(15, 23, 42, 0.56);
    border-radius: 14px;
    background: #ffffff;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.top-loading {
    height: 3px;
    width: 100%;
    border-radius: 999px;
    margin-bottom: 8px;
    background: linear-gradient(90deg, #1f2937 0%, #475569 40%, #94a3b8 100%);
    background-size: 200% 100%;
    animation: loading-shimmer 1s linear infinite;
}

.kicker {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: #94a3b8;
}

.brand {
    margin-top: 2px;
    font-size: 1rem;
    font-weight: 800;
    color: #1f2937;
}

.register-main {
    padding-bottom: 1rem;
}

.stepper {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1fr;
    align-items: center;
    gap: 0.5rem;
}

.step {
    display: grid;
    place-items: center;
    gap: 0.25rem;
    color: #94a3b8;
}

.step span {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border:  1px solid #c0d5eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.step small {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.step.active {
    color: #1f2937;
}

.step.active span {
    border-color: #f53003;
    background: #fff3f2;
    color: #b91c1c;
}

.step-line {
    width: 100%;
    height: 2px;
    background: #d1deec;
}

.step-line.active {
    background: #f53003;
}

.label {
    font-size: 0.86rem;
    font-weight: 700;
    color: #183658;
    margin-bottom: 6px;
    display: inline-block;
}

.field {
    width: 100%;
    border: 1px solid rgba(15, 23, 42, 0.35);
    border-radius: 10px;
    padding: 10px 12px;
    background: #ffffff;
    color: #1f2937;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.field:focus {
    border-color: #f53003;
    box-shadow: 0 0 0 2px rgba(245, 48, 3, 0.15);
}

.file-field {
    padding: 8px;
}

.field-error {
    margin-top: 5px;
    color: #b91c1c;
    font-size: 0.78rem;
}

.btn-outline {
    color: #1f2937;
    border: 1px solid rgba(15, 23, 42, 0.56);
    background: #ffffff;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 14px;
}

.btn-outline:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-fill {
    color: #ffffff;
    border: 1px solid #c12703;
    background: linear-gradient(135deg, #f53003 0%, #d92f08 100%);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 16px;
}

.btn-fill:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.site-footer {
    margin-top: 0.5rem;
    border-top:  1px solid rgba(15, 23, 42, 0.56);
    background: linear-gradient(180deg, #f8fafc 0%, #f8fafc 100%);
    padding-top: 1rem;
}

.footer-shell {
    padding: 0.2rem 0 0;
    color: #64748b;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1rem;
}

.footer-col-brand {
    max-width: 52ch;
}

.footer-brand {
    color: #1f2937;
    font-size: 1rem;
    font-weight: 800;
}

.footer-copy {
    margin-top: 0.5rem;
    color: #64748b;
    line-height: 1.55;
    font-size: 0.9rem;
}

.footer-heading {
    color: #1f2937;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.74rem;
    font-weight: 800;
}

.footer-link-list {
    margin-top: 0.55rem;
    display: grid;
    gap: 0.42rem;
}

.footer-link {
    color: #64748b;
    text-decoration: none;
    font-size: 0.9rem;
}

.footer-link-btn {
    border: none;
    background: none;
    padding: 0;
    text-align: left;
    cursor: pointer;
}

.dialog-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: grid;
    place-items: center;
    z-index: 100;
    padding: 16px;
}

.dialog-card {
    width: min(100%, 420px);
    border-radius: 12px;
    border: 1px solid rgba(239, 68, 68, 0.35);
    background: #fff;
    padding: 18px;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.25);
    text-align: left;
}

.dialog-icon {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    margin-bottom: 8px;
}

.dialog-title {
    color: #1f2937;
    font-size: 1rem;
    font-weight: 700;
}

.dialog-message {
    margin-top: 6px;
    color: #4b5563;
    font-size: 0.9rem;
    line-height: 1.5;
}

.crop-overlay {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.72);
    padding: 1rem;
}

.crop-modal {
    width: min(96vw, 480px);
    max-height: 94vh;
    overflow: auto;
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.35);
    background: #ffffff;
    padding: 1rem;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.25);
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

.step-fade-enter-active,
.step-fade-leave-active {
    transition: opacity 220ms cubic-bezier(0.22, 1, 0.36, 1), transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.step-fade-enter-from,
.step-fade-leave-to {
    opacity: 0;
    transform: translateX(12px);
}

@media (max-width: 640px) {
    .brand {
        font-size: 0.9rem;
    }

    .footer-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .step small {
        font-size: 10px;
    }
}

@media (min-width: 1024px) {
    .register-page {
        font-size: 1.125rem;
    }
}

@keyframes loading-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
