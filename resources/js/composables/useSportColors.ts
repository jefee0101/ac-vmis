export function useSportColors() {
    function normalizeSport(sport: unknown) {
        if (typeof sport === 'string') return sport.toLowerCase().trim()
        if (sport == null) return ''
        return String(sport).toLowerCase().trim()
    }

    function sportColor(sport: unknown) {
        switch (normalizeSport(sport)) {
            case '1':
            case 'basketball':
                return '#f97316'
            case '2':
            case 'volleyball':
                return '#3b82f6'
            case '3':
            case 'football':
                return '#22c55e'
            case '4':
            case 'badminton':
                return '#eab308'
            case '5':
            case 'table tennis':
            case 'table-tennis':
            case 'table_tennis':
                return '#ef4444'
            default:
                return '#6366f1'
        }
    }

    function sportTextColor(sport: unknown) {
        const value = normalizeSport(sport)
        return value === 'badminton' || value === '4' ? '#111827' : '#ffffff'
    }

    function sportLabel(sport: unknown) {
        switch (normalizeSport(sport)) {
            case '1':
            case 'basketball':
                return 'Basketball'
            case '2':
            case 'volleyball':
                return 'Volleyball'
            case '3':
            case 'football':
                return 'Football'
            case '4':
            case 'badminton':
                return 'Badminton'
            case '5':
            case 'table tennis':
            case 'table-tennis':
            case 'table_tennis':
                return 'Table Tennis'
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
