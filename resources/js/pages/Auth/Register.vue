<script setup lang="ts">
import PublicLayout from '@/components/Public/PublicLayout.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { useInertiaLoading } from '@/composables/useInertiaLoading';
import { router } from '@inertiajs/vue3';

const { isLoading } = useInertiaLoading();
function toLogin() {
    router.visit('Login');
}
function registerStudentAthlete() {
    router.visit('Student-AthleteRegister');
}
</script>

<template>
    <PublicLayout title="Register" page-title="Register" page-description="Student-athlete registration starts here.">
        <section class="register-shell">
            <div class="register-grid">
                <section class="public-card register-copy">
                    <p class="copy-kicker">Account Creation</p>
                    <h1>Create Your Student-Athlete Account</h1>
                    <p>Coach accounts are provisioned by the administration team. Public registration is available for student-athletes only.</p>
                </section>

                <section class="public-card register-card">
                    <button @click="registerStudentAthlete" class="role-btn role-btn-fill" :disabled="isLoading">
                        <span class="inline-flex items-center gap-2">
                            <Spinner v-if="isLoading" class="h-4 w-4 text-[#034485]" />
                            {{ isLoading ? 'Opening...' : 'Student-Athlete' }}
                        </span>
                    </button>
                    <p class="login-note">
                        Already registered?
                        <button @click="toLogin" class="login-link" :disabled="isLoading">Login</button>
                    </p>
                </section>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

.register-shell {
    padding: 1.4rem 0 2.2rem;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
}

.register-grid {
    width: 100%;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1rem;
    align-items: center;
}

.register-copy {
    display: grid;
    gap: 0.75rem;
}

.copy-kicker {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.75);
    font-weight: 700;
}

.register-copy h1 {
    margin-top: 0.2rem;
    font-size: 2rem;
    line-height: 1.1;
    color: #ffffff;
    font-weight: 800;
}

.register-copy p {
    margin-top: 0.35rem;
    color: rgba(255, 255, 255, 0.86);
    line-height: 1.65;
    max-width: 52ch;
}

.register-card {
    display: grid;
    gap: 0.75rem;
    align-content: start;
}

.role-btn {
    width: 100%;
    min-height: 66px;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 700;
}

.role-btn-fill {
    border: 1px solid #ffffff;
    background: #ffffff;
    color: #034485;
}

.login-note {
    margin-top: 0.35rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.9rem;
}

.login-link {
    border: none;
    background: none;
    color: #ffffff;
    font-weight: 700;
    margin-left: 0.3rem;
    cursor: pointer;
}

.login-link:hover {
    text-decoration: underline;
}

@media (max-width: 900px) {
    .register-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .register-copy h1 {
        font-size: 1.6rem;
    }
}
</style>
