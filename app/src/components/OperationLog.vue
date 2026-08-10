<template>
	<div class="devnull-log">
		<h3>{{ t('devnull', 'Histórico de Operações') }}</h3>

		<NcLoadingIcon v-if="loading" :size="28" />

		<div v-else-if="operations.length === 0" class="devnull-log__empty">
			{{ t('devnull', 'Nenhuma operação registrada ainda.') }}
		</div>

		<table v-else class="devnull-log__table">
			<thead>
				<tr>
					<th>{{ t('devnull', 'Tipo') }}</th>
					<th>{{ t('devnull', 'Status') }}</th>
					<th>{{ t('devnull', 'Início') }}</th>
					<th>{{ t('devnull', 'Erro') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="op in operations" :key="op.id" :class="'devnull-log__row--' + op.status">
					<td>{{ typeLabel(op.type) }}</td>
					<td>
						<span class="devnull-log__badge" :class="'devnull-log__badge--' + op.status">
							{{ statusLabel(op.status) }}
						</span>
					</td>
					<td>{{ formatDate(op.started_at) }}</td>
					<td>{{ op.error || '—' }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'OperationLog',
	components: { NcLoadingIcon },
	data() {
		return {
			operations: [],
			loading: false,
		}
	},
	mounted() {
		this.fetchLogs()
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
				return new Date(dateStr).toLocaleString('pt-BR')
			} catch {
				return dateStr
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
}

.devnull-log__badge {
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 0.8em;
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

.devnull-log__empty {
	color: var(--color-text-maxcontrast);
	padding: 16px;
	text-align: center;
}
</style>
