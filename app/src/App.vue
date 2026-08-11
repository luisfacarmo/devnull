<template>
	<div id="devnull-app">
		<div class="devnull-header">
			<h2>/dev/null</h2>
			<NcButton type="secondary" @click="refresh">
				<template #icon>
					<ReloadIcon :size="20" />
				</template>
				{{ t('devnull', 'Refresh') }}
			</NcButton>
		</div>

		<DiskList
			:disks="disks"
			:loading="loading"
			:error="error"
			:mount-available="mountAvailable"
			@refresh="fetchDisks" />

		<OperationLog ref="operationLog" />
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import ReloadIcon from 'vue-material-design-icons/Refresh.vue'
import DiskList from './components/DiskList.vue'
import OperationLog from './components/OperationLog.vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'App',
	components: {
		NcButton,
		ReloadIcon,
		DiskList,
		OperationLog,
	},
	data() {
		return {
			disks: [],
			loading: false,
			error: null,
			mountAvailable: true,
		}
	},
	mounted() {
		this.fetchDisks()
	},
	methods: {
		async fetchDisks() {
			this.loading = true
			this.error = null
			try {
				const url = generateOcsUrl('/apps/devnull/api/v1/disks')
				const response = await axios.get(url)
				const data = response.data.ocs?.data ?? {}
				this.disks = data.disks ?? []
				this.mountAvailable = data.capabilities?.mount_available ?? false
			} catch (e) {
				this.error = e.response?.data?.ocs?.meta?.message
					?? t('devnull', 'Falha ao carregar discos')
				console.error('DevNull: fetch disks failed', e)
			} finally {
				this.loading = false
			}
		},
		refresh() {
			this.fetchDisks()
			this.$refs.operationLog?.fetchLogs()
		},
	},
}
</script>
