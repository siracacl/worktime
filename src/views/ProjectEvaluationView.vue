<template>
    <div class="project-evaluation-view">
        <div class="view-header">
            <h2>Projekt-Auswertung</h2>
            <div class="view-header__nav">
                <div class="period-seg" role="group" aria-label="Zeitraum">
                    <button v-for="p in periods"
                        :key="p.value"
                        class="seg-btn"
                        :class="{ active: period === p.value }"
                        @click="setPeriod(p.value)">
                        {{ p.label }}
                    </button>
                </div>
                <div class="period-nav">
                    <NcButton type="tertiary" aria-label="Zurück" @click="shiftPeriod(-1)">
                        <template #icon>
                            <ChevronLeftIcon :size="20" />
                        </template>
                    </NcButton>
                    <span class="period-nav__label">{{ periodLabel }}</span>
                    <NcButton type="tertiary" aria-label="Weiter" @click="shiftPeriod(1)">
                        <template #icon>
                            <ChevronRightIcon :size="20" />
                        </template>
                    </NcButton>
                </div>
            </div>
        </div>

        <div class="ev-filter">
            <div class="ev-filter__label">Projekte</div>
            <div class="ev-chips">
                <button class="ev-chip ev-chip--all" :class="{ on: !selectedProjects.size }" @click="clearProjects">
                    Alle
                </button>
                <button v-for="p in visibleProjects.list"
                    :key="'p' + p.id"
                    class="ev-chip"
                    :class="{ on: selectedProjects.has(p.id) }"
                    :style="selectedProjects.has(p.id) ? { background: p.color, borderColor: p.color } : {}"
                    @click="toggleProject(p.id)">
                    <span class="ev-cdot" :style="{ background: selectedProjects.has(p.id) ? '#fff' : (p.color || 'var(--color-border-dark)') }" />
                    <span>{{ p.name }}</span>
                    <span v-if="selectedProjects.has(p.id)" class="ev-x">×</span>
                </button>
                <button v-if="visibleProjects.hidden" class="ev-chip ev-chip--more" @click="projectsExpanded = true">
                    + {{ visibleProjects.hidden }} weitere
                </button>
                <label class="ev-search">
                    <MagnifyIcon :size="16" />
                    <input v-model="projectSearch" type="text" placeholder="Projekt suchen …">
                </label>
            </div>
        </div>

        <div class="ev-filter">
            <div class="ev-filter__label">Mitarbeitende</div>
            <div class="ev-chips">
                <button class="ev-chip ev-chip--all" :class="{ on: !selectedEmployees.size }" @click="clearEmployees">
                    Alle
                </button>
                <button v-for="e in visibleEmployees.list"
                    :key="'e' + e.id"
                    class="ev-chip ev-chip--emp"
                    :class="{ on: selectedEmployees.has(e.id) }"
                    @click="toggleEmployee(e.id)">
                    <span class="ev-av">{{ initials(e.name) }}</span>
                    <span>{{ e.name }}</span>
                    <span v-if="selectedEmployees.has(e.id)" class="ev-x">×</span>
                </button>
                <button v-if="visibleEmployees.hidden" class="ev-chip ev-chip--more" @click="employeesExpanded = true">
                    + {{ visibleEmployees.hidden }} weitere
                </button>
                <label class="ev-search">
                    <MagnifyIcon :size="16" />
                    <input v-model="employeeSearch" type="text" placeholder="Mitarbeiter suchen …">
                </label>
            </div>
        </div>

        <div class="ev-kpis">
            <div class="kpi-card">
                <div class="kpi-lab">Gebuchte Stunden</div>
                <div class="kpi-num">{{ hours(totals.totalMinutes) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-lab">davon abrechenbar</div>
                <div class="kpi-num">{{ hours(totals.billableMinutes) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-lab">Projekte</div>
                <div class="kpi-num">{{ totals.projectCount }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-lab">Mitarbeitende</div>
                <div class="kpi-num">{{ totals.employeeCount }}</div>
            </div>
        </div>

        <div class="ev-tabs">
            <div class="period-seg" role="group">
                <button class="seg-btn" :class="{ active: tab === 'project' }" @click="tab = 'project'">
                    Nach Projekt
                </button>
                <button class="seg-btn" :class="{ active: tab === 'employee' }" @click="tab = 'employee'">
                    Nach Mitarbeiter
                </button>
                <button class="seg-btn" :class="{ active: tab === 'detail' }" @click="tab = 'detail'">
                    Einzelbuchungen
                </button>
            </div>
            <div class="ev-export">
                <NcButton type="secondary" @click="exportData('csv')">
                    <template #icon>
                        <DownloadIcon :size="18" />
                    </template>
                    CSV
                </NcButton>
                <NcButton type="secondary" @click="exportData('pdf')">
                    <template #icon>
                        <DownloadIcon :size="18" />
                    </template>
                    PDF
                </NcButton>
            </div>
        </div>

        <NcLoadingIcon v-if="loading || (tab === 'detail' && entriesLoading)" class="ev-loading" :size="32" />

        <!-- Aggregiert nach Projekt -->
        <div v-else-if="tab === 'project' && projectRows.length" class="ev-card">
            <table class="ev-table">
                <thead>
                    <tr>
                        <th class="sortable" @click="sortBy('name')">Projekt{{ sortArrow('name') }}</th>
                        <th class="ev-num sortable" @click="sortBy('minutes')">Stunden{{ sortArrow('minutes') }}</th>
                        <th>Anteil</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in projectRows" :key="r.id">
                        <td>
                            <div class="ev-name">
                                <span class="ev-cdot" :style="{ background: r.color || 'var(--color-border-dark)' }" />
                                <span class="ev-emp-name">{{ r.name }}</span>
                                <span v-if="r.code" class="ev-muted">({{ r.code }})</span>
                                <span v-if="r.isBillable" class="ev-badge">abrechenbar</span>
                            </div>
                        </td>
                        <td class="ev-num">{{ hours(r.minutes) }}</td>
                        <td>
                            <div class="ev-share">
                                <div class="ev-bar"><span :style="{ width: pct(r.minutes) + '%', background: r.color || 'var(--color-primary-element)' }" /></div>
                                <span class="ev-muted">{{ pct(r.minutes) }} %</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Gesamt</td>
                        <td class="ev-num">{{ hours(totals.totalMinutes) }}</td>
                        <td class="ev-muted">100 %</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Aggregiert nach Mitarbeiter -->
        <div v-else-if="tab === 'employee' && employeeRows.length" class="ev-card">
            <table class="ev-table">
                <thead>
                    <tr>
                        <th class="sortable" @click="sortBy('name')">Mitarbeiter{{ sortArrow('name') }}</th>
                        <th class="ev-num sortable" @click="sortBy('minutes')">Stunden{{ sortArrow('minutes') }}</th>
                        <th>Anteil</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in employeeRows" :key="r.id">
                        <td>
                            <div class="ev-name">
                                <span class="ev-av">{{ initials(r.name) }}</span>
                                <span class="ev-emp-name">{{ r.name }}</span>
                            </div>
                        </td>
                        <td class="ev-num">{{ hours(r.minutes) }}</td>
                        <td>
                            <div class="ev-share">
                                <div class="ev-bar"><span :style="{ width: pct(r.minutes) + '%' }" /></div>
                                <span class="ev-muted">{{ pct(r.minutes) }} %</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Gesamt</td>
                        <td class="ev-num">{{ hours(totals.totalMinutes) }}</td>
                        <td class="ev-muted">100 %</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Einzelbuchungen -->
        <div v-else-if="tab === 'detail' && detailRows.length" class="ev-card">
            <table class="ev-table ev-entries">
                <thead>
                    <tr>
                        <th class="sortable" @click="sortBy('date')">Datum{{ sortArrow('date') }}</th>
                        <th>Projekt</th>
                        <th class="sortable" @click="sortBy('name')">Mitarbeiter{{ sortArrow('name') }}</th>
                        <th class="ev-num sortable" @click="sortBy('minutes')">Stunden{{ sortArrow('minutes') }}</th>
                        <th>Beschreibung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="entry in detailRows" :key="entry.id">
                        <td>{{ formatDate(entry.date) }}</td>
                        <td>
                            <div class="ev-name">
                                <span class="ev-cdot" :style="{ background: entry.color || 'var(--color-border-dark)' }" />
                                <span>{{ entry.projectName || 'Kein Projekt' }}</span>
                            </div>
                        </td>
                        <td>{{ entry.employeeName || 'Unbekannt' }}</td>
                        <td class="ev-num">{{ hours(entry.minutes) }}</td>
                        <td class="ev-muted">{{ entry.description || '' }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Gesamt</td>
                        <td class="ev-num">{{ hours(detailTotalMinutes) }}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-else class="ev-empty">
            Für diese Auswahl liegen keine Buchungen vor.
        </div>
    </div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import ReportService from '../services/ReportService.js'
import { formatMinutes } from '../utils/timeUtils.js'
import { formatDate as formatDateUtil, getMonthName } from '../utils/dateUtils.js'
import { showErrorMessage } from '../utils/errorHandler.js'

export default {
    name: 'ProjectEvaluationView',
    components: {
        NcButton,
        NcLoadingIcon,
        ChevronLeftIcon,
        ChevronRightIcon,
        DownloadIcon,
        MagnifyIcon,
    },
    data() {
        const now = new Date()
        return {
            year: now.getFullYear(),
            month: now.getMonth() + 1,
            period: 'month',
            tab: 'project',
            loading: false,
            entriesLoading: false,
            rows: [],
            entries: [],
            entriesLoadedKey: null,
            selectedProjects: new Set(),
            selectedEmployees: new Set(),
            sort: { key: 'minutes', dir: -1 },
            // Chip scaling: only the top N unselected chips are shown by default;
            // the rest is reachable via search or "+ N weitere".
            topN: 8,
            projectSearch: '',
            employeeSearch: '',
            projectsExpanded: false,
            employeesExpanded: false,
        }
    },
    computed: {
        periods() {
            return [
                { value: 'month', label: 'Monat' },
                { value: 'quarter', label: 'Quartal' },
                { value: 'year', label: 'Jahr' },
            ]
        },
        periodLabel() {
            if (this.period === 'year') return String(this.year)
            if (this.period === 'quarter') return `Q${Math.floor((this.month - 1) / 3) + 1} ${this.year}`
            return `${getMonthName(this.month)} ${this.year}`
        },
        projectChips() {
            const seen = {}
            for (const r of this.rows) {
                if (!seen[r.projectId]) {
                    seen[r.projectId] = {
                        id: r.projectId,
                        name: r.projectName || 'Kein Projekt',
                        color: r.color,
                    }
                }
            }
            return Object.values(seen).sort((a, b) => a.name.localeCompare(b.name))
        },
        employeeChips() {
            const seen = {}
            for (const r of this.rows) {
                if (!seen[r.employeeId]) {
                    seen[r.employeeId] = { id: r.employeeId, name: r.employeeName || 'Unbekannt' }
                }
            }
            return Object.values(seen).sort((a, b) => a.name.localeCompare(b.name))
        },
        projectMinutes() {
            const m = {}
            for (const r of this.rows) m[r.projectId] = (m[r.projectId] || 0) + r.minutes
            return m
        },
        employeeMinutes() {
            const m = {}
            for (const r of this.rows) m[r.employeeId] = (m[r.employeeId] || 0) + r.minutes
            return m
        },
        visibleProjects() {
            return this.buildChips(this.projectChips, this.selectedProjects, this.projectSearch, this.projectsExpanded, this.projectMinutes)
        },
        visibleEmployees() {
            return this.buildChips(this.employeeChips, this.selectedEmployees, this.employeeSearch, this.employeesExpanded, this.employeeMinutes)
        },
        filteredRows() {
            return this.rows.filter(r =>
                (!this.selectedProjects.size || this.selectedProjects.has(r.projectId))
                && (!this.selectedEmployees.size || this.selectedEmployees.has(r.employeeId)),
            )
        },
        totals() {
            const fr = this.filteredRows
            const projects = new Set()
            const employees = new Set()
            let total = 0
            let billable = 0
            for (const r of fr) {
                total += r.minutes
                if (r.isBillable) billable += r.minutes
                if (r.projectId > 0) projects.add(r.projectId)
                employees.add(r.employeeId)
            }
            return { totalMinutes: total, billableMinutes: billable, projectCount: projects.size, employeeCount: employees.size }
        },
        projectRows() {
            const byProject = {}
            for (const r of this.filteredRows) {
                if (!byProject[r.projectId]) {
                    byProject[r.projectId] = {
                        id: r.projectId,
                        name: r.projectName || 'Kein Projekt',
                        code: r.projectCode,
                        color: r.color,
                        isBillable: r.isBillable,
                        minutes: 0,
                    }
                }
                byProject[r.projectId].minutes += r.minutes
            }
            return this.sortAgg(Object.values(byProject))
        },
        employeeRows() {
            const byEmp = {}
            for (const r of this.filteredRows) {
                if (!byEmp[r.employeeId]) {
                    byEmp[r.employeeId] = { id: r.employeeId, name: r.employeeName || 'Unbekannt', minutes: 0 }
                }
                byEmp[r.employeeId].minutes += r.minutes
            }
            return this.sortAgg(Object.values(byEmp))
        },
        detailRows() {
            const filtered = this.entries.filter(e =>
                (!this.selectedProjects.size || this.selectedProjects.has(e.projectId))
                && (!this.selectedEmployees.size || this.selectedEmployees.has(e.employeeId)),
            )
            const s = this.sort
            return filtered.slice().sort((a, b) => {
                if (s.key === 'name') return s.dir * (a.employeeName || '').localeCompare(b.employeeName || '')
                if (s.key === 'minutes') return s.dir * (a.minutes - b.minutes)
                return s.dir * a.date.localeCompare(b.date)
            })
        },
        detailTotalMinutes() {
            return this.detailRows.reduce((sum, e) => sum + e.minutes, 0)
        },
    },
    watch: {
        period() { this.refresh() },
        tab(value) { if (value === 'detail') this.ensureEntries() },
    },
    created() {
        this.refresh()
    },
    methods: {
        hours(minutes) { return `${formatMinutes(minutes || 0)} h` },
        formatDate(date) { return formatDateUtil(date) },
        initials(name) {
            return name.split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase()
        },
        /**
         * Chip display list: selected items first (always visible), then the
         * unselected ones filtered by the search and ranked by hours, capped at
         * topN unless searching or expanded.
         */
        buildChips(items, selected, search, expanded, mins) {
            const sel = items.filter(it => selected.has(it.id))
            const q = search.trim().toLowerCase()
            let rest = items.filter(it => !selected.has(it.id))
            if (q) {
                rest = rest.filter(it => it.name.toLowerCase().includes(q))
            }
            rest.sort((a, b) => (mins[b.id] || 0) - (mins[a.id] || 0))
            const limited = (expanded || q) ? rest : rest.slice(0, this.topN)
            return { list: [...sel, ...limited], hidden: rest.length - limited.length }
        },
        pct(minutes) {
            const base = this.totals.totalMinutes || 1
            return Math.round((minutes / base) * 100)
        },
        sortAgg(arr) {
            const s = this.sort
            return arr.sort((a, b) => s.key === 'name'
                ? s.dir * a.name.localeCompare(b.name)
                : s.dir * (a.minutes - b.minutes))
        },
        sortArrow(key) {
            if (this.sort.key !== key) return ''
            return this.sort.dir < 0 ? ' ▼' : ' ▲'
        },
        sortBy(key) {
            if (this.sort.key === key) {
                this.sort = { key, dir: -this.sort.dir }
            } else {
                this.sort = { key, dir: (key === 'name' || key === 'date') ? 1 : -1 }
            }
        },
        toggleProject(id) {
            const s = new Set(this.selectedProjects)
            if (s.has(id)) {
                s.delete(id)
            } else {
                s.add(id)
            }
            this.selectedProjects = s
        },
        clearProjects() { this.selectedProjects = new Set() },
        toggleEmployee(id) {
            const s = new Set(this.selectedEmployees)
            if (s.has(id)) {
                s.delete(id)
            } else {
                s.add(id)
            }
            this.selectedEmployees = s
        },
        clearEmployees() { this.selectedEmployees = new Set() },
        setPeriod(value) { this.period = value },
        shiftPeriod(direction) {
            if (this.period === 'year') {
                this.year += direction
            } else if (this.period === 'quarter') {
                this.month += direction * 3
            } else {
                this.month += direction
            }
            while (this.month > 12) { this.month -= 12; this.year += 1 }
            while (this.month < 1) { this.month += 12; this.year -= 1 }
            this.refresh()
        },
        refresh() {
            this.load()
            this.entries = []
            this.entriesLoadedKey = null
            this.projectSearch = ''
            this.employeeSearch = ''
            this.projectsExpanded = false
            this.employeesExpanded = false
            if (this.tab === 'detail') this.ensureEntries()
        },
        periodKey() { return `${this.year}-${this.month}-${this.period}` },
        async load() {
            this.loading = true
            try {
                const data = await ReportService.getProjectEvaluation({
                    year: this.year, month: this.month, period: this.period,
                })
                this.rows = data?.rows || []
            } catch (error) {
                showErrorMessage(error.message || 'Fehler beim Laden der Auswertung')
            } finally {
                this.loading = false
            }
        },
        async ensureEntries() {
            if (this.entriesLoadedKey === this.periodKey()) return
            this.entriesLoading = true
            try {
                const data = await ReportService.getProjectEntries({
                    year: this.year, month: this.month, period: this.period,
                })
                this.entries = data?.entries || []
                this.entriesLoadedKey = this.periodKey()
            } catch (error) {
                showErrorMessage(error.message || 'Fehler beim Laden der Auswertung')
            } finally {
                this.entriesLoading = false
            }
        },
        exportData(format) {
            ReportService.downloadProjectExport(format, {
                year: this.year,
                month: this.month,
                period: this.period,
                projectIds: [...this.selectedProjects],
                employeeIds: [...this.selectedEmployees],
                mode: this.tab, // 'project' | 'employee' | 'detail' — export matches the current view
            })
        },
    },
}
</script>

<style scoped>
.project-evaluation-view {
    padding: 20px;
    padding-left: 50px;
    max-width: 1400px;
}

.view-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.view-header h2 {
    margin: 0;
}

.view-header__nav {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 16px;
}

.period-seg {
    display: flex;
    background: var(--color-background-dark);
    border-radius: var(--border-radius-element, 8px);
    padding: 3px;
}

.seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    background: none;
    border: none;
    padding: 6px 14px;
    border-radius: var(--border-radius-element, 8px);
    cursor: pointer;
}

.seg-btn.active {
    background: var(--color-main-background);
    color: var(--color-primary-element);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}

.period-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.period-nav__label {
    font-size: 1.1em;
    font-weight: 500;
    min-width: 9rem;
    text-align: center;
}

/* Chip filters */
.ev-filter {
    margin-bottom: 14px;
}

.ev-filter__label {
    color: var(--color-text-maxcontrast);
    font-size: 0.85em;
    margin-bottom: 8px;
}

.ev-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.ev-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1.5px solid var(--color-border-dark);
    background: var(--color-main-background);
    color: var(--color-main-text);
    border-radius: 999px;
    padding: 6px 13px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
}

.ev-chip:hover {
    background: var(--color-background-hover);
}

.ev-chip.on {
    border-color: transparent;
    color: #fff;
}

.ev-chip--all.on {
    background: var(--color-primary-element);
    border-color: var(--color-primary-element);
    color: var(--color-primary-element-text);
}

.ev-chip--emp.on {
    background: var(--color-text-maxcontrast);
    border-color: var(--color-text-maxcontrast);
}

.ev-cdot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ev-x {
    margin-left: 2px;
    font-weight: 700;
    opacity: 0.8;
}

.ev-chip--more {
    border-style: dashed;
    color: var(--color-text-maxcontrast);
    font-weight: 600;
}

.ev-search {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1.5px solid var(--color-border-dark);
    border-radius: 999px;
    padding: 4px 12px;
    background: var(--color-main-background);
    min-width: 190px;
    color: var(--color-text-maxcontrast);
}

.ev-search input {
    border: none;
    outline: none;
    background: none;
    font: inherit;
    font-size: 13px;
    color: var(--color-main-text);
    width: 100%;
}

.ev-av {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-background-dark);
    color: var(--color-text-maxcontrast);
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ev-chip--emp.on .ev-av {
    background: rgba(255, 255, 255, 0.9);
    color: #333;
}

/* KPI cards */
.ev-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin: 18px 0 20px;
}

.kpi-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border-dark, var(--color-border));
    border-radius: var(--border-radius-large, 12px);
    padding: 14px 16px;
}

.kpi-lab {
    color: var(--color-text-maxcontrast);
    font-size: 0.85em;
    margin-bottom: 4px;
}

.kpi-num {
    font-size: 1.5em;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.ev-tabs {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 14px;
}

.ev-export {
    display: flex;
    gap: 8px;
}

/* Table card */
.ev-card {
    background: var(--color-main-background);
    border: 1px solid var(--color-border-dark, var(--color-border));
    border-radius: var(--border-radius-large, 12px);
    padding: 8px 16px;
    overflow-x: auto;
}

.ev-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.ev-table th {
    text-align: left;
    padding: 10px 12px;
    border-bottom: 2px solid var(--color-border-dark, var(--color-border));
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}

.ev-table th.ev-num {
    text-align: right;
}

/* Aggregierte Tabs: Name-Spalte nimmt die Restbreite */
.ev-table:not(.ev-entries) th:first-child,
.ev-table:not(.ev-entries) td:first-child {
    width: 100%;
}

/* Einzelbuchungen: feste Spaltenbreiten */
.ev-entries {
    table-layout: fixed;
}

.ev-entries th:nth-child(1) { width: 11%; }  /* Datum */
.ev-entries th:nth-child(2) { width: 24%; }  /* Projekt */
.ev-entries th:nth-child(3) { width: 18%; }  /* Mitarbeiter */
.ev-entries th:nth-child(4) { width: 10%; }  /* Stunden */
.ev-entries th:nth-child(5) { width: 37%; }  /* Beschreibung */

.ev-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--color-border);
}

.ev-table tbody tr:hover {
    background: var(--color-background-hover);
}

.ev-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.ev-table th.sortable:hover {
    color: var(--color-main-text);
}

.ev-num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.ev-name {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ev-emp-name {
    font-weight: 600;
}

.ev-muted {
    color: var(--color-text-maxcontrast);
}

.ev-badge {
    background: var(--color-success-hover, #e5f5e9);
    color: var(--color-success-text, #2f7d49);
    border-radius: 12px;
    padding: 1px 8px;
    font-size: 0.8em;
    font-weight: 600;
    white-space: nowrap;
}

.ev-share {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ev-bar {
    height: 6px;
    flex: 0 0 120px;
    width: 120px;
    border-radius: var(--border-radius-element, 8px);
    background: var(--color-background-dark);
    overflow: hidden;
}

.ev-bar > span {
    display: block;
    height: 100%;
    border-radius: var(--border-radius-element, 8px);
    background: var(--color-primary-element);
}

.ev-table tfoot td {
    font-weight: 600;
    border-top: 2px solid var(--color-border-dark, var(--color-border));
    border-bottom: none;
}

.ev-table tfoot tr:hover {
    background: none;
}

.ev-loading,
.ev-empty {
    margin-top: 40px;
    text-align: center;
    color: var(--color-text-maxcontrast);
}
</style>
