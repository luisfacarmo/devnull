<template>
	<div id="devnull-app">
		<div class="devnull-header">
			<h2>DevNull</h2>
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
			@refresh="fetchDisks"
			@mount="handleMount"
			@unmount="handleUnmount" />
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import ReloadIcon from 'vue-material-design-icons/Refresh.vue'
import DiskList from './components/DiskList.vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'App',
	components: {
		NcButton,
		ReloadIcon,
		DiskList,
	},
	data() {
		return {
			disks: [],
			loading: false,
			error: null,
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
				this.disks = response.data.ocs?.data?.disks ?? []
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
		},
	},
}
</script>
