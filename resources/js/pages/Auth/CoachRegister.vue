<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useInertiaLoading } from '@/composables/useInertiaLoading';
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import PublicLayout from '@/components/Public/PublicLayout.vue';

type Step = 1 | 2;

const draftKey = 'ac-vmis-coach-registration-draft-v1';
const step = ref<Step>(1);
const isSubmitting = ref(false);
const isStepTransitioning = ref(false);
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

    first_name: '',
    middle_name: '',
    last_name: '',
    phone_number: '',
    date_of_birth: '',
    gender: '' as '' | 'Male' | 'Female' | 'Other',
    home_address: '',
});

const fieldErrors = reactive<Record<string, string>>({});

const fullNamePreview = computed(() => [form.first_name, form.last_name].filter(Boolean).join(' ').trim());
const avatarName = computed(() => form.avatar?.name ?? 'No file selected');
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

function toLogin() {
    router.visit('/Login');
}

function handleEnter(event: KeyboardEvent) {
    if (isSubmitting.value) return;
    const target = event.target as HTMLElement | null;
    if (!target) return;
    if (target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') return;
    if (target instanceof HTMLButtonElement || target instanceof HTMLAnchorElement) return;
    if (target instanceof HTMLInputElement) {
        const type = target.type;
        if (['file', 'button', 'submit', 'checkbox', 'radio'].includes(type)) return;
    }

    event.preventDefault();
    if (step.value < 2) {
        nextStep();
    } else {
        submit();
    }
}

function openModal(title: string, message: string) {
    modal.title = title;
    modal.message = message;
    modal.open = true;
}

function closeModal() {
    modal.open = false;
}

function setFieldError(field: string, message: string) {
    fieldErrors[field] = message;
}

function clearFieldError(field: string) {
    fieldErrors[field] = '';
}

function validateEmail(email: string) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateField(field: string): boolean {
    const value = (form as Record<string, unknown>)[field];

    if (field === 'email') {
        const text = String(value ?? '').trim();
        if (!text) {
            setFieldError(field, 'Email is required.');
            return false;
        }
        if (!validateEmail(text)) {
            setFieldError(field, 'Enter a valid email address.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'password') {
        const text = String(value ?? '').trim();
        if (!text) {
            setFieldError(field, 'Password is required.');
            return false;
        }
        if (text.length < 6) {
            setFieldError(field, 'Password must be at least 6 characters.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'password_confirmation') {
        const text = String(value ?? '').trim();
        if (!text) {
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

    if (['first_name', 'last_name'].includes(field)) {
        if (!String(value ?? '').trim()) {
            setFieldError(field, 'This field is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    if (field === 'phone_number') {
        const text = String(value ?? '').trim();
        if (text && text.length < 7) {
            setFieldError(field, 'Phone number looks too short.');
            return false;
        }
        clearFieldError(field);
        return true;
    }

    return true;
}

watch(
    () => [form.email, form.password, form.password_confirmation, form.first_name, form.last_name, form.phone_number],
    () => {
        if (form.email) validateField('email');
        if (form.password) validateField('password');
        if (form.password_confirmation) validateField('password_confirmation');
        if (form.first_name) validateField('first_name');
        if (form.last_name) validateField('last_name');
        if (form.phone_number) validateField('phone_number');
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
        1: ['email', 'password', 'password_confirmation'],
        2: ['first_name', 'last_name'],
    };

    return checks[currentStep].every((field) => validateField(field));
}

function nextStep() {
    if (!validateStep(step.value)) {
        openModal('Incomplete Step', 'Please fix the highlighted fields before continuing.');
        return;
    }

    isStepTransitioning.value = true;
    step.value = 2;
    if (stepTransitionTimer) clearTimeout(stepTransitionTimer);
    stepTransitionTimer = setTimeout(() => {
        isStepTransitioning.value = false;
    }, 260);
}

function previousStep() {
    isStepTransitioning.value = true;
    step.value = 1;
    if (stepTransitionTimer) clearTimeout(stepTransitionTimer);
    stepTransitionTimer = setTimeout(() => {
        isStepTransitioning.value = false;
    }, 260);
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

function onAvatarInputChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        fieldErrors.avatar = 'Please select a valid image file.';
        input.value = '';
        return;
    }

    fieldErrors.avatar = '';
    resetCropState();
    revokeBlobUrl(cropSourceUrl.value);
    cropSourceUrl.value = URL.createObjectURL(file);
    cropModalOpen.value = true;
    input.value = '';
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

    form.avatar = new File([blob], `coach-avatar-${Date.now()}.jpg`, { type: 'image/jpeg' });
    closeCropModal();
}

function saveDraft() {
    const payload = {
        ...form,
        avatar: null,
    };
    localStorage.setItem(draftKey, JSON.stringify(payload));
    openModal('Draft Saved', 'Your coach registration draft was saved on this browser.');
}

function restoreDraft() {
    const raw = localStorage.getItem(draftKey);
    if (!raw) return;

    try {
        const parsed = JSON.parse(raw) as Partial<typeof form>;
        Object.assign(form, parsed);
    } catch {
        localStorage.removeItem(draftKey);
    }
}

function clearDraft() {
    localStorage.removeItem(draftKey);
}

function submit() {
    if (!validateStep(1) || !validateStep(2)) {
        openModal('Cannot Submit', 'Please complete required fields first.');
        return;
    }

    const formData = new FormData();
    formData.append('role', 'coach');
    formData.append('email', form.email);
    formData.append('password', form.password);
    formData.append('password_confirmation', form.password_confirmation);
    formData.append('first_name', form.first_name);
    formData.append('middle_name', form.middle_name);
    formData.append('last_name', form.last_name);
    formData.append('phone_number', form.phone_number);
    formData.append('date_of_birth', form.date_of_birth);
    formData.append('gender', form.gender);
    formData.append('home_address', form.home_address);

    if (form.avatar) {
        formData.append('avatar', form.avatar);
    }

    router.post('/RegisterCoachData', formData, {
        forceFormData: true,
        onStart: () => {
            isSubmitting.value = true;
        },
        onFinish: () => {
            isSubmitting.value = false;
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

            const generic = fieldErrors.error || fieldErrors.email || 'Please review the form and try again.';
            openModal('Registration Error', generic);
        },
    });
}

onMounted(() => {
    restoreDraft();
});

onBeforeUnmount(() => {
    if (stepTransitionTimer) {
        clearTimeout(stepTransitionTimer);
    }
    revokeBlobUrl(cropSourceUrl.value);
    revokeBlobUrl(avatarPreviewUrl.value);
    removeCropDragListeners();
});
</script>

<template>
    <PublicLayout
        title="Coach Registration"
        page-title="Coach Registration"
        page-description="2-step signup with instant validation."
    >
        <main class="register-main px-4 py-8 sm:px-6 lg:px-10" @keydown.enter="handleEnter">
            <div class="mx-auto w-full max-w-3xl public-card register-card">
                <h1 class="register-title">Coach Registration</h1>
                <p class="register-subtitle">2-step signup with instant validation.</p>

                <div class="mt-6 stepper">
                    <div class="step" :class="{ active: step >= 1 }"><span>1</span><small>Account</small></div>
                    <div class="step-line" :class="{ active: step >= 2 }"></div>
                    <div class="step" :class="{ active: step >= 2 }"><span>2</span><small>Profile</small></div>
                </div>

                <section v-if="isStepTransitioning" class="mt-7 grid gap-3">
                    <Skeleton class="h-12" />
                    <Skeleton class="h-12" />
                    <Skeleton class="h-20" />
                </section>

                <transition v-else name="step-fade" mode="out-in">
                    <section v-if="step === 1" key="coach-step-1" class="mt-7 grid gap-4">
                    <div>
                        <label class="label">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            :class="['field', { 'is-error': fieldErrors.email }]"
                            placeholder="name@example.com"
                            @blur="validateField('email')"
                        />
                        <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Password</label>
                            <div class="relative">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :class="['field', 'pr-10', { 'is-error': fieldErrors.password }]"
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
                                    :class="['field', 'pr-10', { 'is-error': fieldErrors.password_confirmation }]"
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
                        <label class="label">Avatar (Optional)</label>
                        <input
                            type="file"
                            :class="['field', 'file-field', { 'is-error': fieldErrors.avatar }]"
                            accept="image/*"
                            @change="onAvatarInputChange"
                        />
                        <p class="mt-1 text-xs text-slate-500">Selected: {{ avatarName }}</p>
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

                    <section v-else key="coach-step-2" class="mt-7 grid gap-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="label">First Name</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                :class="['field', { 'is-error': fieldErrors.first_name }]"
                                @blur="validateField('first_name')"
                            />
                            <p v-if="fieldErrors.first_name" class="field-error">{{ fieldErrors.first_name }}</p>
                        </div>
                        <div>
                            <label class="label">Middle Name (Optional)</label>
                            <input v-model="form.middle_name" type="text" class="field" />
                        </div>
                        <div>
                            <label class="label">Last Name</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                :class="['field', { 'is-error': fieldErrors.last_name }]"
                                @blur="validateField('last_name')"
                            />
                            <p v-if="fieldErrors.last_name" class="field-error">{{ fieldErrors.last_name }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">Full name preview: {{ fullNamePreview || 'Not set yet' }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Phone Number (Optional)</label>
                            <input
                                v-model="form.phone_number"
                                type="text"
                                :class="['field', { 'is-error': fieldErrors.phone_number }]"
                                placeholder="09XXXXXXXXX"
                                @blur="validateField('phone_number')"
                            />
                            <p v-if="fieldErrors.phone_number" class="field-error">{{ fieldErrors.phone_number }}</p>
                        </div>
                        <div>
                            <label class="label">Date of Birth (Optional)</label>
                            <input v-model="form.date_of_birth" type="date" class="field" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Gender (Optional)</label>
                            <select v-model="form.gender" class="field">
                                <option value="" disabled>Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Home Address (Optional)</label>
                            <input v-model="form.home_address" type="text" class="field" />
                        </div>
                    </div>

                    <button type="button" class="btn-outline w-fit" :disabled="isSubmitting" @click="saveDraft">Save As Draft</button>
                    </section>
                </transition>

                <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-[#e2e8f0] pt-4">
                    <button type="button" class="btn-outline" :disabled="step === 1 || isSubmitting" @click="previousStep">Previous</button>

                    <div class="flex items-center gap-2">
                        <button v-if="step < 2" type="button" class="btn-fill" :disabled="isSubmitting" @click="nextStep">Continue</button>
                        <button v-else type="button" class="btn-fill" :disabled="isSubmitting" @click="submit">
                            <span class="inline-flex items-center gap-2">
                                <Spinner v-if="isSubmitting" class="h-4 w-4 text-white" />
                                {{ isSubmitting ? 'Submitting...' : 'Create Account' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </main>

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
                    <p v-if="cropError" class="error-inline text-xs">{{ cropError }}</p>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="closeCropModal">Cancel</button>
                    <button type="button" class="btn-fill" @click="applyCroppedAvatar">Use Cropped Photo</button>
                </div>
            </div>
        </div>
    </PublicLayout>
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
    background: rgba(255, 255, 255, 0.9);
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

.register-card {
    color: #ffffff;
}

.register-title {
    text-align: center;
    font-size: 2rem;
    font-weight: 800;
    color: #ffffff;
}

.register-subtitle {
    margin-top: 0.45rem;
    text-align: center;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.86);
}

.register-card h2,
.register-card h3 {
    color: #ffffff;
}

.register-card p,
.register-card small {
    color: rgba(255, 255, 255, 0.82);
}

.stepper {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.5rem;
}

.step {
    display: grid;
    place-items: center;
    gap: 0.25rem;
    color: rgba(255, 255, 255, 0.7);
}

.step span {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #ffffff;
}

.step small {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.step.active {
    color: #ffffff;
}

.step.active span {
    border-color: #ffffff;
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

.step-line {
    width: 100%;
    height: 2px;
    background: rgba(255, 255, 255, 0.3);
}

.step-line.active {
    background: #ffffff;
}

.label {
    font-size: 0.86rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 6px;
    display: inline-block;
}

.field {
    width: 100%;
    border: 1px solid rgba(3, 68, 133, 0.25);
    border-radius: 10px;
    padding: 10px 12px;
    background: #ffffff;
    color: #1f2937;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.field:focus {
    border-color: rgba(3, 68, 133, 0.55);
    box-shadow: 0 0 0 2px rgba(3, 68, 133, 0.2);
}

.file-field {
    padding: 8px;
}

.field-error {
    margin-top: 5px;
    color: #ffffff;
    -webkit-text-stroke: 0.45px #dc2626;
    text-shadow: 0 0 1px rgba(220, 38, 38, 0.65);
    font-size: 0.78rem;
}

.error-inline {
    color: #ffffff;
    -webkit-text-stroke: 0.45px #dc2626;
    text-shadow: 0 0 1px rgba(220, 38, 38, 0.65);
}

.field.is-error {
    border-color: #dc2626;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2);
}

.btn-outline {
    color: #034485;
    border: 1px solid rgba(3, 68, 133, 0.35);
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
    border: 1px solid #034485;
    background: #034485;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 14px;
}

.register-card .btn-outline {
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.6);
    background: transparent;
}

.register-card .btn-fill {
    color: #034485;
    border-color: #ffffff;
    background: #ffffff;
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
