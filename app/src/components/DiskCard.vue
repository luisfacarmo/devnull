<template>
	<div class="devnull-disk-card" :class="{ 'devnull-disk-card--mounted': disk.mounted, 'devnull-disk-card--loading': loading }">
		<div class="devnull-disk-card__header">
			<HarddiskIcon :size="24" />
			<span class="devnull-disk-card__name">
				{{ disk.label || disk.name }}
			</span>
			<span v-if="disk.mounted" class="devnull-disk-card__badge">
				{{ t('devnull', 'Montado') }}
			</span>
		</div>

		<div class="devnull-disk-card__details">
			<span>{{ t('devnull', 'Device') }}</span>
			<span>/dev/{{ disk.name }}</span>

			<span>{{ t('devnull', 'Size') }}</span>
			<span>{{ disk.size }}</span>

			<span>{{ t('devnull', 'Filesystem') }}</span>
			<span>{{ disk.fstype || t('devnull', 'Desconhecido') }}</span>

			<span v-if="disk.model">{{ t('devnull', 'Model') }}</span>
			<span v-if="disk.model">{{ disk.model }}</span>

			<span v-if="disk.serial">{{ t('devnull', 'Serial') }}</span>
			<span v-if="disk.serial">{{ disk.serial }}</span>

			<span v-if="disk.mountpoint">{{ t('devnull', 'Montado em') }}</span>
			<span v-if="disk.mountpoint">{{ disk.mountpoint }}</span>
		</div>

		<div v-if="error" class="devnull-disk-card__error">
			{{ error }}
		</div>

		<div class="devnull-disk-card__actions">
			<NcButton v-if="!disk.mounted"
				type="primary"
				:disabled="loading || !mountAvailable"
				@click="handleMount">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<PlayIcon v-else :size="20" />
				</template>
				{{ t('devnull', 'Montar') }}
			</NcButton>

			<template v-else>
				<NcButton
					type="secondary"
					:disabled="loading"
					@click="handleIngest">
					<template #icon>
						<NcLoadingIcon v-if="ingesting" :size="20" />
						<CogIcon v-else :size="20" />
					</template>
					{{ t('devnull', 'Processar') }}
				</NcButton>

				<NcButton
					type="error"
					:disabled="loading"
					@click="handleEject">
					<template #icon>
						<NcLoadingIcon v-if="loading && !ingesting" :size="20" />
						<EjectIcon v-else :size="20" />
					</template>
					{{ t('devnull', 'Ejetar') }}
				</NcButton>
			</template>
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import HarddiskIcon from 'vue-material-design-icons/Harddisk.vue'
import PlayIcon from 'vue-material-design-icons/Play.vue'
import EjectIcon from 'vue-material-design-icons/Eject.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'

export default {
	name: 'DiskCard',
	components: {
		NcButton,
		NcLoadingIcon,
		HarddiskIcon,
		PlayIcon,
		EjectIcon,
		CogIcon,
	},
	props: {
		disk: {
			type: Object,
			required: true,
		},
		mountAvailable: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			loading: false,
			ingesting: false,
			error: null,
		}
	},
	methods: {
		async handleMount() {
			this.loading = true
			this.error = null
			try {
				const url = generateOcsUrl('/apps/devnull/api/v1/mount')
				await axios.post(url, { device: this.disk.name })
				this.$emit('refresh')
			} catch (e) {
				this.error = e.response?.data?.ocs?.data?.error
					?? t('devnull', 'Falha ao montar')
				console.error('DevNull: mount failed', e)
			} finally {
				this.loading = false
			}
		},
		async handleEject() {
			this.loading = true
			this.error = null
			try {
				const url = generateOcsUrl('/apps/devnull/api/v1/unmount')
				await axios.post(url, { device: this.disk.name })
				this.$emit('refresh')
			} catch (e) {
				this.error = e.response?.data?.ocs?.data?.error
					?? t('devnull', 'Falha ao ejetar')
				console.error('DevNull: unmount failed', e)
			} finally {
				this.loading = false
			}
		},
		async handleIngest() {
			this.ingesting = true
			this.loading = true
			this.error = null
			try {
				const url = generateOcsUrl('/apps/devnull/api/v1/ingest')
				const response = await axios.post(url, { device: this.disk.name })
				const data = response.data.ocs?.data
				if (data && !data.success) {
					this.error = t('devnull', 'Pipeline concluiu com erros')
				}
				this.$emit('refresh')
			} catch (e) {
				this.error = e.response?.data?.ocs?.data?.error
					?? t('devnull', 'Falha no processamento')
				console.error('DevNull: ingest failed', e)
			} finally {
				this.ingesting = false
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.devnull-disk-card--mounted {
	border-color: var(--color-success);
	border-width: 2px;
}

.devnull-disk-card--loading {
	opacity: 0.7;
	pointer-events: none;
}

.devnull-disk-card__badge {
	background: var(--color-success);
	color: white;
	font-size: 0.75em;
	padding: 2px 8px;
	border-radius: 10px;
	margin-left: auto;
}

.devnull-disk-card__error {
	color: var(--color-error);
	font-size: 0.85em;
	margin-top: 8px;
	padding: 4px 8px;
	background: var(--color-error-background, #fdecea);
	border-radius: 4px;
}
</style>
