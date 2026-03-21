export type CoachNavItem = {
  key: string
  label: string
  route: string
  icon: string
  mobileLabel?: string
}

export const coachPrimaryNav: CoachNavItem[] = [
  { key: 'dashboard', label: 'Dashboard', route: '/coach/dashboard', icon: 'layout-grid', mobileLabel: 'Home' },
  { key: 'team', label: 'My Team', route: '/coach/team', icon: 'users', mobileLabel: 'Team' },
  { key: 'operations', label: 'Operations', route: '/coach/operations', icon: 'clipboard-check', mobileLabel: 'Ops' },
  { key: 'schedule', label: 'Schedule', route: '/coach/schedule', icon: 'calendar', mobileLabel: 'Schedule' },
  { key: 'academics', label: 'Academics', route: '/coach/academics', icon: 'graduation-cap', mobileLabel: 'Academics' },
]

export const coachSecondaryNav: CoachNavItem[] = []
