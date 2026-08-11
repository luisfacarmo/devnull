<template>
	<div class="devnull-log">
		<div class="devnull-log__header">
			<h3>{{ t('devnull', 'Atividade') }}</h3>
			<NcButton type="tertiary" :disabled="loading" @click="fetchLogs">
				<template #icon>
					<RefreshIcon :size="20" :class="{ 'spin': loading }" />
				</template>
			</NcButton>
		</div>

		<!-- Stats summary -->
		<div v-if="operations.length > 0" class="devnull-log__stats">
			<span class="devnull-log__stat">
				<strong>{{ stats.total }}</strong> {{ t('devnull', 'operações') }}
			</span>
			<span v-if="stats.completed > 0" class="devnull-log__stat devnull-log__stat--success">
				{{ stats.completed }} {{ t('devnull', 'concluídas') }}
			</span>
			<span v-if="stats.failed > 0" class="devnull-log__stat devnull-log__stat--error">
				{{ stats.failed }} {{ t('devnull', 'falharam') }}
			</span>
			<span v-if="stats.running > 0" class="devnull-log__stat devnull-log__stat--running">
				{{ stats.running }} {{ t('devnull', 'em andamento') }}
			</span>
		</div>

		<!-- Filter -->
		<div v-if="operations.length > 3" class="devnull-log__filter">
			<select v-model="filterType">
				<option value="">{{ t('devnull', 'Todos os tipos') }}</option>
				<option value="mount">{{ t('devnull', 'Mount') }}</option>
				<option value="unmount">{{ t('devnull', 'Eject') }}</option>
				<option value="ingest">{{ t('devnull', 'Pipeline') }}</option>
				<option value="scan">{{ t('devnull', 'Scan') }}</option>
			</select>
			<select v-model="filterStatus">
				<option value="">{{ t('devnull', 'Todos os status') }}</option>
				<option value="completed">{{ t('devnull', 'Concluído') }}</option>
				<option value="failed">{{ t('devnull', 'Falhou') }}</option>
				<option value="running">{{ t('devnull', 'Rodando') }}</option>
			</select>
		</div>

		<NcLoadingIcon v-if="loading && operations.length === 0" :size="28" />

		<div v-else-if="operations.length === 0" class="devnull-log__empty">
			{{ t('devnull', 'Nenhuma operação registrada ainda.') }}
			<p class="devnull-log__hint">
				{{ t('devnull', 'Operações aparecerão aqui após montar ou processar um disco.') }}
			</p>
		</div>

		<table v-else-if="filteredOperations.length > 0" class="devnull-log__table">
			<thead>
				<tr>
					<th>{{ t('devnull', 'Tipo') }}</th>
					<th>{{ t('devnull', 'Status') }}</th>
					<th>{{ t('devnull', 'Início') }}</th>
					<th>{{ t('devnull', 'Duração') }}</th>
					<th>{{ t('devnull', 'Erro') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="op in filteredOperations" :key="op.id" :class="'devnull-log__row--' + op.status">
					<td>
						<span class="devnull-log__type-icon">{{ typeIcon(op.type) }}</span>
						{{ typeLabel(op.type) }}
					</td>
					<td>
						<span class="devnull-log__badge" :class="'devnull-log__badge--' + op.status">
							{{ statusLabel(op.status) }}
						</span>
					</td>
					<td>{{ formatDate(op.started_at) }}</td>
					<td>{{ formatDuration(op.started_at, op.finished_at) }}</td>
					<td class="devnull-log__error-cell">{{ op.error || '—' }}</td>
				</tr>
			</tbody>
		</table>

		<div v-else class="devnull-log__empty">
			{{ t('devnull', 'Nenhum resultado com esses filtros.') }}
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import RefreshIcon from 'vue-material-design-icons/Refresh.vue'

export default {
	name: 'OperationLog',
	components: { NcButton, NcLoadingIcon, RefreshIcon },
	data() {
		return {
			operations: [],
			loading: false,
			filterType: '',
			filterStatus: '',
			refreshInterval: null,
		}
	},
	computed: {
		filteredOperations() {
			return this.operations.filter(op => {
				if (this.filterType && op.type !== this.filterType) return false
				if (this.filterStatus && op.status !== this.filterStatus) return false
				return true
			})
		},
		stats() {
			const ops = this.operations
			return {
				total: ops.length,
				completed: ops.filter(o => o.status === 'completed').length,
				failed: ops.filter(o => o.status === 'failed').length,
				running: ops.filter(o => o.status === 'running').length,
			}
		},
	},
	mounted() {
		this.fetchLogs()
		// Auto-refresh every 30s
		this.refreshInterval = setInterval(() => this.fetchLogs(), 30000)
	},
	beforeDestroy() {
		if (this.refreshInterval) {
			clearInterval(this.refreshInterval)
		}
	},
	methods: {
		async fetchLogs() {
			this.loading = true
			try {
				const url = generateOcsUrl('/apps/devnull/api/v1/logs')
				const response = await axios.get(url)
				this.operations = response.data.ocs?.data?.operations ?? []
			} catch (e) {
				console.error('DevNull: fetch logs failed', e)
			} finally {
				this.loading = false
			}
		},
		typeLabel(type) {
			const labels = {
				mount: 'Montar',
				unmount: 'Ejetar',
				ingest: 'Pipeline',
				scan: 'Scan',
				dedup: 'Deduplicar',
				classify: 'Classificar',
			}
			return labels[type] || type
		},
		typeIcon(type) {
			const icons = {
				mount: '💾',
				unmount: '⏏',
				ingest: '⚙',
				scan: '🔍',
				dedup: '🔄',
				classify: '🤖',
			}
			return icons[type] || '📋'
		},
		statusLabel(status) {
			const labels = {
				pending: 'Pendente',
				running: 'Rodando',
				completed: 'Concluído',
				failed: 'Falhou',
			}
			return labels[status] || status
		},
		formatDate(dateStr) {
			if (!dateStr) return '—'
			try {
				return new Date(dateStr).toLocaleString('pt-BR', {
					day: '2-digit',
					month: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
				})
			} catch {
				return dateStr
			}
		},
		formatDuration(start, end) {
			if (!start || !end) return '—'
			try {
				const ms = new Date(end) - new Date(start)
				if (ms < 1000) return '< 1s'
				if (ms < 60000) return Math.round(ms / 1000) + 's'
				return Math.round(ms / 60000) + 'min'
			} catch {
				return '—'
			}
		},
	},
}
</script>

<style scoped>
.devnull-log {
	margin-top: 32px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.devnull-log__header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 12px;
}

.devnull-log__header h3 {
	margin: 0;
	flex: 1;
}

.devnull-log__stats {
	display: flex;
	gap: 16px;
	margin-bottom: 12px;
	font-size: 0.85em;
}

.devnull-log__stat--success {
	color: var(--color-success);
}

.devnull-log__stat--error {
	color: var(--color-error);
}

.devnull-log__stat--running {
	color: var(--color-warning);
}

.devnull-log__filter {
	display: flex;
	gap: 8px;
	margin-bottom: 12px;
}

.devnull-log__filter select {
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: 4px;
	font-size: 0.85em;
}

.devnull-log__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.9em;
}

.devnull-log__table th,
.devnull-log__table td {
	padding: 8px 12px;
	text-align: left;
	border-bottom: 1px solid var(--color-border);
}

.devnull-log__table th {
	font-weight: bold;
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}

.devnull-log__type-icon {
	margin-right: 4px;
}

.devnull-log__badge {
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 0.8em;
	white-space: nowrap;
}

.devnull-log__badge--completed {
	background: var(--color-success);
	color: white;
}

.devnull-log__badge--failed {
	background: var(--color-error);
	color: white;
}

.devnull-log__badge--running {
	background: var(--color-warning);
	color: black;
}

.devnull-log__badge--pending {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.devnull-log__error-cell {
	max-width: 200px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--color-error);
	font-size: 0.85em;
}

.devnull-log__empty {
	color: var(--color-text-maxcontrast);
	padding: 24px 16px;
	text-align: center;
}

.devnull-log__hint {
	font-size: 0.85em;
	margin-top: 8px;
}

.spin {
	animation: spin 1s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}
</style>
