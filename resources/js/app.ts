import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { Transition, createApp, h } from 'vue';
import { initTheme, setStoredTheme } from '@/composables/useTheme';
import SessionExpiredToast from '@/components/ui/SessionExpiredToast.vue';
import { useSessionExpired } from '@/composables/useSessionExpired';

const appName = import.meta.env.VITE_APP_NAME || 'ACVMIS';

function applyThemeFromProps(pageProps: any) {
    const storedTheme = typeof window !== 'undefined' ? window.localStorage.getItem('ac-vmis-theme-mode') : null;
    if (storedTheme === 'light' || storedTheme === 'dark') {
        setStoredTheme(storedTheme);
        return true;
    }
    if (storedTheme === 'blue') {
        setStoredTheme('dark');
        return true;
    }
    const theme = pageProps?.auth?.settings?.theme_preference;
    if (theme === 'light' || theme === 'dark') {
        setStoredTheme(theme);
        return true;
    }
    if (theme === 'blue') {
        setStoredTheme('dark');
        return true;
    }
    return false;
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        if (!applyThemeFromProps(props.initialPage?.props)) {
            initTheme();
        }
        router.on('success', (event) => {
            applyThemeFromProps(event.detail.page.props);
        });
        const { showSessionExpired } = useSessionExpired();

        const isAuthExpired = (status?: number) => status === 401 || status === 419;

        router.on('error', (event: any) => {
            const status = event?.detail?.response?.status;
            if (isAuthExpired(status)) {
                showSessionExpired();
            }
        });

        router.on('finish', (event: any) => {
            const status = event?.detail?.visit?.response?.status;
            if (isAuthExpired(status)) {
                showSessionExpired();
            }
        });

        if (typeof window !== 'undefined') {
            window.addEventListener('inertia:exception', (event: any) => {
                const status = event?.detail?.response?.status;
                if (isAuthExpired(status)) {
                    showSessionExpired();
                }
            });
        }

        createApp({
            render: () =>
                h('div', { class: 'app-shell' }, [
                    h(
                        Transition,
                        { name: 'route-fade', mode: 'out-in' },
                        {
                            default: () => h(App, { ...props, key: props.initialPage.url }),
                        },
                    ),
                    h(SessionExpiredToast),
                ]),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
