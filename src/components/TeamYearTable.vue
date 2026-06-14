<template>
    <div class="team-year-table">
        <div v-for="member in report"
            :key="member.employee.id"
            class="member-card">
            <!-- Header: Name + weekly hours -->
            <div class="member-card__header">
                <NcAvatar :user="member.employee.userId"
                    :display-name="member.employee.fullName"
                    :size="44" />
                <span class="member-card__name">{{ member.employee.fullName }}</span>
                <span class="member-card__hours">{{ member.employee.weeklyHours }} {{ t('worktime', 'Std./Woche') }}</span>
                <NcButton v-if="canManage"
                    type="secondary"
                    class="member-card__payout"
                    @click="openPayoutDialog(member)">
                    <template #icon>
                        <CashMinusIcon :size="18" />
                    </template>
                    {{ t('worktime', 'Auszahlen') }}
                </NcButton>
            </div>

            <!-- Data table -->
            <table>
                <thead>
                    <tr class="month-header">
                        <th class="col-label">{{ t('worktime', 'Art') }}</th>
                        <th v-for="m in 12"
                            :key="m"
                            class="col-month">
                            {{ getMonthNameShort(m) }}
                        </th>
                        <th class="col-total">{{ t('worktime', 'Gesamt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1: Vacation -->
                    <tr class="row-data">
                        <td class="col-label">{{ t('worktime', 'Urlaub') }}</td>
                        <td v-for="m in member.months"
                            :key="m.month"
                            class="col-month">
                            <span v-if="m.vacationDays > 0" class="vacation-days">{{ formatVacationDays(m.vacationDays) }}</span>
                        </td>
                        <td class="col-total">
                            <strong>{{ formatVacationDays(member.vacationStats.used) }}/{{ formatVacationDays(member.vacationStats.total) }}</strong>
                        </td>
                    </tr>
                    <!-- Row 2: Overtime -->
                    <tr class="row-data">
                        <td class="col-label">{{ t('worktime', 'Stunden') }}</td>
                        <td v-for="m in member.months"
                            :key="m.month"
                            class="col-month"
                            :class="overtimeClass(m.overtimeMinutes)">
                            <span v-if="m.overtimeMinutes !== null">{{ formatOvertimeShort(m.overtimeMinutes) }}</span>
                        </td>
                        <td class="col-total" :class="overtimeClass(member.totalOvertimeMinutes)">
                            <strong>{{ formatOvertimeShort(member.totalOvertimeMinutes) }}</strong>
                        </td>
                    </tr>
                    <!-- Row 3: Status -->
                    <tr class="row-data">
                        <td class="col-label">{{ t('worktime', 'Status') }}</td>
                        <td v-for="m in member.months"
                            :key="m.month"
                            class="col-month">
                            <CheckCircleIcon v-if="m.status === 'approved'"
                                :size="20"
                                class="status-icon status-approved"
                                :title="t('worktime', 'Genehmigt')" />
                            <ClockOutlineIcon v-else-if="m.status === 'submitted'"
                                :size="20"
                                class="status-icon status-submitted"
                                :class="{ clickable: m.canApprove }"
                                :title="m.canApprove ? t('worktime', 'Klicken zum Genehmigen') : t('worktime', 'Eingereicht')"
                                @click="m.canApprove ? onApproveClick(member, m.month) : null" />
                            <CloseCircleIcon v-else-if="m.status === 'rejected'"
                                :size="20"
                                class="status-icon status-rejected"
                                :title="t('worktime', 'Abgelehnt')" />
                            <SendIcon v-if="canManage && m.canSubmit && (m.status === 'draft' || m.status === 'rejected')"
                                :size="17"
                                class="status-icon status-submit clickable"
                                :title="t('worktime', 'Monat für Mitarbeiter einreichen')"
                                @click="onSubmitClick(member, m.month)" />
                        </td>
                        <td class="col-total" />
                    </tr>
                </tbody>
            </table>

            <!-- Recorded payouts for this member -->
            <div v-if="canManage && member.payouts && member.payouts.length" class="member-payouts">
                <div class="member-payouts__title">{{ t('worktime', 'Erfasste Auszahlungen') }}</div>
                <div v-for="p in member.payouts" :key="p.id" class="member-payouts__row">
                    <span class="member-payouts__info">
                        {{ getMonthName(p.month) }} {{ year }} · <strong class="negative">−{{ formatPayoutHours(p.minutes) }} h</strong>
                        <span v-if="p.note" class="member-payouts__note">– {{ p.note }}</span>
                    </span>
                    <NcButton type="tertiary"
                        :aria-label="t('worktime', 'Auszahlung stornieren')"
                        :title="t('worktime', 'Auszahlung stornieren')"
                        @click="onCancelPayout(member, p)">
                        <template #icon>
                            <DeleteIcon :size="18" />
                        </template>
                    </NcButton>
                </div>
            </div>
        </div>

        <!-- Approval Dialog -->
        <NcDialog v-if="approveDialog.show"
            :name="t('worktime', 'Monat genehmigen')"
            @closing="approveDialog.show = false">
            <p>
                {{ getMonthName(approveDialog.month) }} {{ t('worktime', 'für') }}
                <strong>{{ approveDialog.employeeName }}</strong> {{ t('worktime', 'genehmigen?') }}
            </p>
            <template #actions>
                <NcButton type="tertiary" @click="approveDialog.show = false">
                    {{ t('worktime', 'Abbrechen') }}
                </NcButton>
                <NcButton type="primary"
                    :disabled="approveDialog.loading"
                    @click="confirmApprove">
                    <template v-if="approveDialog.loading" #icon>
                        <NcLoadingIcon :size="20" />
                    </template>
                    {{ t('worktime', 'Genehmigen') }}
                </NcButton>
            </template>
        </NcDialog>

        <!-- Payout Dialog -->
        <NcDialog v-if="payoutDialog.show"
            :name="t('worktime', 'Überstunden auszahlen')"
            @closing="payoutDialog.show = false">
            <div class="payout-form">
                <p class="payout-form__hint">
                    {{ t('worktime', 'Mitarbeiter: {name}', { name: payoutDialog.employeeName }) }} ·
                    {{ t('worktime', 'Aktueller Saldo: {hours} h', { hours: payoutBalanceHours }) }}
                </p>
                <label class="payout-field">
                    <span>{{ t('worktime', 'Stichmonat') }}</span>
                    <select v-model.number="payoutDialog.month">
                        <option v-for="mm in 12" :key="mm" :value="mm">{{ getMonthName(mm) }} {{ year }}</option>
                    </select>
                </label>
                <label class="payout-field">
                    <span>{{ t('worktime', 'Auszuzahlende Stunden') }}</span>
                    <input v-model="payoutDialog.hours" type="number" step="0.25" min="0">
                </label>
                <label class="payout-field">
                    <span>{{ t('worktime', 'Grund') }}</span>
                    <input v-model="payoutDialog.note" type="text" :placeholder="t('worktime', 'z. B. Auszahlung Überstunden')">
                </label>
            </div>
            <template #actions>
                <NcButton type="tertiary" @click="payoutDialog.show = false">
                    {{ t('worktime', 'Abbrechen') }}
                </NcButton>
                <NcButton type="primary" :disabled="payoutDialog.loading" @click="confirmPayout">
                    <template v-if="payoutDialog.loading" #icon>
                        <NcLoadingIcon :size="20" />
                    </template>
                    {{ t('worktime', 'Speichern') }}
                </NcButton>
            </template>
        </NcDialog>

        <!-- Submit-month Dialog -->
        <NcDialog v-if="submitDialog.show"
            :name="t('worktime', 'Monat einreichen')"
            @closing="submitDialog.show = false">
            <p>
                {{ getMonthName(submitDialog.month) }} {{ year }} {{ t('worktime', 'für') }}
                <strong>{{ submitDialog.employeeName }}</strong> {{ t('worktime', 'einreichen?') }}
            </p>
            <template #actions>
                <NcButton type="tertiary" @click="submitDialog.show = false">
                    {{ t('worktime', 'Abbrechen') }}
                </NcButton>
                <NcButton type="primary" :disabled="submitDialog.loading" @click="confirmSubmit">
                    <template v-if="submitDialog.loading" #icon>
                        <NcLoadingIcon :size="20" />
                    </template>
                    {{ t('worktime', 'Einreichen') }}
                </NcButton>
            </template>
        </NcDialog>

        <!-- Cancel-payout Dialog -->
        <NcDialog v-if="cancelDialog.show"
            :name="t('worktime', 'Auszahlung stornieren')"
            @closing="cancelDialog.show = false">
            <p>
                {{ t('worktime', 'Auszahlung') }} <strong>{{ cancelDialog.label }}</strong>
                {{ t('worktime', 'für') }} <strong>{{ cancelDialog.employeeName }}</strong>
                {{ t('worktime', 'stornieren? Der Saldo wird wiederhergestellt.') }}
            </p>
            <template #actions>
                <NcButton type="tertiary" @click="cancelDialog.show = false">
                    {{ t('worktime', 'Abbrechen') }}
                </NcButton>
                <NcButton type="error" :disabled="cancelDialog.loading" @click="confirmCancelPayout">
                    <template v-if="cancelDialog.loading" #icon>
                        <NcLoadingIcon :size="20" />
                    </template>
                    {{ t('worktime', 'Stornieren') }}
                </NcButton>
            </template>
        </NcDialog>
    </div>
</template>

<script>
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import CloseCircleIcon from 'vue-material-design-icons/CloseCircle.vue'
import SendIcon from 'vue-material-design-icons/Send.vue'
import CashMinusIcon from 'vue-material-design-icons/CashMinus.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import { getMonthNameShort, getMonthName, getCurrentMonth, getLocale } from '../utils/dateUtils.js'
import { formatMinutes } from '../utils/timeUtils.js'
import { formatVacationDays } from '../utils/formatters.js'
import TimeEntryService from '../services/TimeEntryService.js'
import OvertimePayoutService from '../services/OvertimePayoutService.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'TeamYearTable',
    components: {
        NcAvatar,
        NcButton,
        NcDialog,
        NcLoadingIcon,
        CheckCircleIcon,
        ClockOutlineIcon,
        CloseCircleIcon,
        SendIcon,
        CashMinusIcon,
        DeleteIcon,
    },
    props: {
        report: {
            type: Array,
            required: true,
        },
        year: {
            type: Number,
            required: true,
        },
        canManage: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['approved', 'reload'],
    data() {
        return {
            approveDialog: {
                show: false,
                employeeId: null,
                employeeName: '',
                month: null,
                loading: false,
            },
            payoutDialog: {
                show: false,
                employeeId: null,
                employeeName: '',
                month: getCurrentMonth(),
                hours: '',
                note: '',
                balanceMinutes: 0,
                loading: false,
            },
            submitDialog: {
                show: false,
                employeeId: null,
                employeeName: '',
                month: null,
                loading: false,
            },
            cancelDialog: {
                show: false,
                id: null,
                label: '',
                employeeName: '',
                loading: false,
            },
        }
    },
    computed: {
        payoutBalanceHours() {
            return (this.payoutDialog.balanceMinutes / 60).toLocaleString(getLocale(), { maximumFractionDigits: 1 })
        },
    },
    methods: {
        getMonthNameShort,
        getMonthName,
        formatVacationDays,
        formatOvertimeShort(minutes) {
            if (minutes === null || minutes === undefined) return '--'
            const sign = minutes >= 0 ? '+' : ''
            return sign + formatMinutes(minutes)
        },
        overtimeClass(minutes) {
            if (minutes === null || minutes === undefined) return ''
            if (minutes > 0) return 'positive'
            if (minutes < 0) return 'negative'
            return ''
        },
        onApproveClick(member, month) {
            this.approveDialog = {
                show: true,
                employeeId: member.employee.id,
                employeeName: member.employee.fullName,
                month,
                loading: false,
            }
        },
        async confirmApprove() {
            this.approveDialog.loading = true
            try {
                const result = await TimeEntryService.approveMonth(
                    this.approveDialog.employeeId,
                    this.year,
                    this.approveDialog.month,
                )
                showSuccess(t('worktime', '{count} Einträge genehmigt', { count: result.approved }))
                this.approveDialog.show = false
                this.$emit('approved')
            } catch (error) {
                console.error('Failed to approve month:', error)
                showError(t('worktime', 'Fehler beim Genehmigen'))
            } finally {
                this.approveDialog.loading = false
            }
        },
        openPayoutDialog(member) {
            const balance = member.totalOvertimeMinutes || 0
            this.payoutDialog = {
                show: true,
                employeeId: member.employee.id,
                employeeName: member.employee.fullName,
                month: getCurrentMonth(),
                hours: balance > 0 ? Number((balance / 60).toFixed(2)) : '',
                note: '',
                balanceMinutes: balance,
                loading: false,
            }
        },
        async confirmPayout() {
            const hours = Number(this.payoutDialog.hours)
            if (!hours || Number.isNaN(hours) || hours <= 0) {
                showError(t('worktime', 'Bitte einen positiven Stundenbetrag eingeben.'))
                return
            }
            if (!this.payoutDialog.note || !this.payoutDialog.note.trim()) {
                showError(t('worktime', 'Bitte einen Grund angeben.'))
                return
            }
            this.payoutDialog.loading = true
            try {
                await OvertimePayoutService.create(
                    this.payoutDialog.employeeId,
                    this.year,
                    this.payoutDialog.month,
                    Math.round(hours * 60),
                    this.payoutDialog.note.trim(),
                )
                showSuccess(t('worktime', 'Auszahlung gespeichert.'))
                this.payoutDialog.show = false
                this.$emit('reload')
            } catch (error) {
                showError(error.message || t('worktime', 'Speichern fehlgeschlagen.'))
            } finally {
                this.payoutDialog.loading = false
            }
        },
        onSubmitClick(member, month) {
            this.submitDialog = {
                show: true,
                employeeId: member.employee.id,
                employeeName: member.employee.fullName,
                month,
                loading: false,
            }
        },
        async confirmSubmit() {
            this.submitDialog.loading = true
            try {
                const result = await TimeEntryService.submitMonth(
                    this.submitDialog.employeeId,
                    this.year,
                    this.submitDialog.month,
                )
                showSuccess(t('worktime', '{count} Einträge eingereicht', { count: result?.submitted ?? 0 }))
                this.submitDialog.show = false
                this.$emit('reload')
            } catch (error) {
                console.error('Failed to submit month:', error)
                showError(error.message || t('worktime', 'Fehler beim Einreichen'))
            } finally {
                this.submitDialog.loading = false
            }
        },
        formatPayoutHours(minutes) {
            return formatMinutes(Math.abs(minutes))
        },
        onCancelPayout(member, payout) {
            this.cancelDialog = {
                show: true,
                id: payout.id,
                label: `${getMonthName(payout.month)} ${this.year} · ${formatMinutes(Math.abs(payout.minutes))} h`,
                employeeName: member.employee.fullName,
                loading: false,
            }
        },
        async confirmCancelPayout() {
            this.cancelDialog.loading = true
            try {
                await OvertimePayoutService.cancel(this.cancelDialog.id)
                showSuccess(t('worktime', 'Auszahlung storniert.'))
                this.cancelDialog.show = false
                this.$emit('reload')
            } catch (error) {
                showError(error.message || t('worktime', 'Stornieren fehlgeschlagen.'))
            } finally {
                this.cancelDialog.loading = false
            }
        },
    },
}
</script>

<style scoped>
.team-year-table {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Member card – same pattern as dashboard-card / stat-card */
.member-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border-dark, var(--color-border));
    border-radius: var(--border-radius-large, 12px);
    padding: 20px;
    overflow-x: auto;
}

.member-card__header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.member-card__name {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-main-text);
}

.member-card__hours {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
}

table {
    width: 100%;
    border-collapse: collapse;
}

/* Month column headers – match reference tables (maxcontrast text, 2px divider) */
.month-header th {
    padding: 10px 4px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    border-bottom: 2px solid var(--color-border-dark, var(--color-border));
    white-space: nowrap;
}

/* Label column (first col) */
.col-label {
    text-align: left;
    padding: 10px 12px 10px 0 !important;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
    min-width: 60px;
}

/* Month data columns */
.col-month {
    text-align: center;
    min-width: 58px;
    padding: 10px 4px;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

/* Total column */
.col-total {
    text-align: center;
    min-width: 75px;
    padding: 10px 4px;
    font-size: 13px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    border-left: 1px solid var(--color-border);
}

/* Data rows */
.row-data td {
    border-bottom: 1px solid var(--color-border);
}

.row-data:last-child td {
    border-bottom: none;
}

/* Vacation */
.vacation-days {
    font-weight: 600;
}

/* Overtime colors */
.positive {
    color: var(--color-success-text);
}

.negative {
    color: var(--color-error-text);
}

/* Status icons */
.status-icon {
    display: inline-flex;
}

.status-approved {
    color: var(--wt-vacation, #4a9d63);
}

.status-submitted {
    color: var(--wt-holiday, #c98b3a);
}

.status-submitted.clickable {
    cursor: pointer;
}

.status-submitted.clickable:hover {
    color: #a06d00;
}

.status-rejected {
    color: var(--wt-sick, #cc4b42);
}

.status-submit {
    color: var(--color-primary-element);
    cursor: pointer;
    margin-left: 4px;
    vertical-align: middle;
}

.status-submit:hover {
    opacity: 0.7;
}

.member-card__payout {
    margin-left: auto;
}

.member-payouts {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
}

.member-payouts__title {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 6px;
}

.member-payouts__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
    font-size: 13px;
}

.member-payouts__note {
    color: var(--color-text-maxcontrast);
}

.payout-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 4px 2px 8px;
    min-width: 280px;
}

.payout-form__hint {
    margin: 0;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
}

.payout-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
}

.payout-field select,
.payout-field input {
    width: 100%;
}

.muted {
    color: var(--color-text-maxcontrast);
}
</style>
