<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

type FooterLink = {
  label: string
  href: string
}

const props = defineProps<{
  title: string
  description: string
  links: FooterLink[]
  bottomNav?: boolean
}>()

const currentYear = new Date().getFullYear()
</script>

<template>
  
  <footer
    class="role-footer mt-10 border-t border-slate-200 bg-white/95 backdrop-blur"
    :class="props.bottomNav ? 'pb-[calc(env(safe-area-inset-bottom,0px)+5.25rem)] md:pb-5' : 'pb-5'"
  >
    <div class="mx-auto w-full max-w-[1600px] px-4 pt-6 sm:px-6 lg:px-10">
      <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr_1fr_1.1fr]">
        <section class="space-y-3">
          <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">AC-VMIS</p>
          <div>
            <p class="text-[13px] font-semibold text-slate-900">{{ props.title }}</p>
            <p class="mt-1 text-[11px] text-slate-500">{{ props.description }}</p>
          </div>
          <div class="space-y-2 text-[11px] text-slate-500">
            <p class="inline-flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="M3 7l9 6 9-6" />
              </svg>
              <span>varsity.support@asiancollege.edu.ph</span>
            </p>
            <p class="inline-flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.77.66 2.6a2 2 0 0 1-.45 2.11L8 9.9a16 16 0 0 0 6.1 6.1l1.47-1.32a2 2 0 0 1 2.11-.45c.83.32 1.7.54 2.6.66A2 2 0 0 1 22 16.92z" />
              </svg>
              <span>+63 000 000 0000</span>
            </p>
          </div>
        </section>

        <nav aria-label="Workspace Links" class="space-y-3">
          <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Workspace</p>
          <div class="grid gap-2">
            <Link
              v-for="item in props.links"
              :key="item.href"
              :href="item.href"
              class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-semibold text-slate-600 transition hover:border-slate-200 hover:text-[#1f2937]"
            >
              {{ item.label }}
            </Link>
          </div>
        </nav>

        <nav aria-label="Resources" class="space-y-3">
          <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Resources</p>
          <div class="grid gap-2">
            <Link href="/policies" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-semibold text-slate-600 transition hover:border-slate-200 hover:text-[#1f2937]">
              Policies
            </Link>
            <Link href="/privacy-policy" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-semibold text-slate-600 transition hover:border-slate-200 hover:text-[#1f2937]">
              Privacy Policy
            </Link>
            <Link href="/terms-of-use" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-semibold text-slate-600 transition hover:border-slate-200 hover:text-[#1f2937]">
              Terms of Use
            </Link>
          </div>
        </nav>

        <section aria-label="Institution Mission" class="space-y-3">
          <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Institution</p>
          <div class="space-y-3 text-[11px] text-slate-500">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-700">Vision</p>
              <p>To be a transformative educational institution committed to the success of its graduates through quality instruction, relevant research, and strong community engagement.</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-700">Mission</p>
              <p>To educate and develop globally competitive future leaders.</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-700">Core Values</p>
              <p>Academic Excellence</p>
              <p>Integrity</p>
              <p>Self-Leadership</p>
            </div>
          </div>
        </section>
      </div>

      <div class="mt-5 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-400">
        <span>v1.0 • © {{ currentYear }} Asian College</span>
        <span>Varsity Management Information System</span>
      </div>
    </div>
  </footer>
</template>
