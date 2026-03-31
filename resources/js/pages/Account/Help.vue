<script setup lang="ts">
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import CoachDashboard from '@/pages/Coaches/CoachDashboard.vue'
import StudentAthleteDashboard from '@/pages/StudentAthletes/StudentAthleteDashboard.vue'
import { Head, Link } from '@inertiajs/vue3'

defineOptions({
  layout: (h: any, page: any) => {
    const role = String(page?.props?.auth?.user?.role ?? '')
    const layout = role === 'admin' ? AdminDashboard : role === 'coach' ? CoachDashboard : StudentAthleteDashboard
    return h(layout, [page])
  },
})
</script>

<template>
  <Head title="Help & Support" />

  <div class="settings-page space-y-6">
      <div>
        <Link href="/account/settings" class="back-pill">Back</Link>
      </div>

      <section class="rounded-2xl border border-[#034485]/40 bg-white p-6">
        <h2 class="section-title">Help &amp; Support</h2>
        <p class="settings-muted mt-2 text-sm text-slate-600">
          Static help content for common questions and support links.
        </p>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">Getting Started</p>
            <p class="text-xs text-slate-500">Quick steps for new accounts, approvals, and daily workflows.</p>
          </div>
          <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">Account Access</p>
            <p class="text-xs text-slate-500">Password help and account recovery guidance.</p>
          </div>
          <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">Support Contact</p>
            <p class="text-xs text-slate-500">Email support@asiancollege.edu or contact the admin office.</p>
          </div>
          <div class="rounded-xl border border-[#034485]/30 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">Reporting Issues</p>
            <p class="text-xs text-slate-500">Capture screenshots and describe the steps to reproduce.</p>
          </div>
        </div>
      </section>
  </div>
</template>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-weight: 600;
}

.settings-muted {
  color: #64748b;
}

.back-pill {
  border-radius: 999px;
  background: #034485;
  padding: 0.4rem 1rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #ffffff;
  transition: background 0.2s ease;
  display: inline-flex;
  align-items: center;
}

.back-pill:hover {
  background: #04519f;
}
</style>
