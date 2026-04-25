export const supportedSports = ['basketball', 'soccer', 'volleyball'] as const

export function useSportColors() {
    function normalizeSport(sport: unknown) {
        let normalized = ''

        if (typeof sport === 'string') {
            normalized = sport
                .toLowerCase()
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
        }
        if (sport != null && normalized === '') {
            normalized = String(sport).toLowerCase().trim()
        }

        if (normalized === 'football') {
            return 'soccer'
        }

        return normalized
    }

    function sportColor(sport: unknown) {
        switch (normalizeSport(sport)) {
            case 'basketball':
                return '#f97316'
            case 'volleyball':
                return '#3b82f6'
            case 'soccer':
                return '#16a34a'
            default:
                return '#64748b'
        }
    }

    function sportTextColor(sport: unknown) {
        return '#ffffff'
    }

    function sportLabel(sport: unknown) {
        switch (normalizeSport(sport)) {
            case 'basketball':
                return 'Basketball'
            case 'volleyball':
                return 'Volleyball'
            case 'soccer':
                return 'Soccer'
            default:
                return 'Unknown'
        }
    }

    return {
        normalizeSport,
        sportColor,
        sportTextColor,
        sportLabel,
    }
}
