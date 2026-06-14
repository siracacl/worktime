import api, { handleApiError } from './api.js'

export default {
    async list(employeeId, year) {
        try {
            const response = await api.get(`/overtime-payouts/${year}/${employeeId}`)
            return response.data
        } catch (error) {
            handleApiError(error)
        }
    },

    async create(employeeId, year, month, minutes, note) {
        try {
            const response = await api.post('/overtime-payouts', {
                employeeId,
                year,
                month,
                minutes,
                note,
            })
            return response.data
        } catch (error) {
            handleApiError(error)
        }
    },

    async cancel(id) {
        try {
            const response = await api.delete(`/overtime-payouts/${id}`)
            return response.data
        } catch (error) {
            handleApiError(error)
        }
    },
}
