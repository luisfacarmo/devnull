<template>
	<div>
		<!-- Loading state -->
		<NcLoadingIcon v-if="loading" :size="44" />

		<!-- Error state -->
		<NcEmptyContent v-else-if="error"
			:name="t('devnull', 'Error loading disks')"
			:description="error">
			<template #icon>
				<AlertIcon :size="64" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$emit('refresh')">
					{{ t('devnull', 'Retry') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<!-- Empty state -->
		<NcEmptyContent v-else-if="disks.length === 0"
			:name="t('devnull', 'No disks detected')"
			:description="t('devnull', 'Connect an external drive to your server and click Refresh.')">
			<template #icon>
				<HarddiskIcon :size="64" />
			</template>
		</NcEmptyContent>

		<!-- Disk grid -->
		<div v-else class="devnull-disk-grid">
			<DiskCard
				v-for="disk in disks"
				:key="disk.name"
				:disk="disk"
				@refresh="$emit('refresh')" />
		</div>
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import AlertIcon from 'vue-material-design-icons/AlertCircleOutline.vue'
import HarddiskIcon from 'vue-material-design-icons/Harddisk.vue'
import DiskCard from './DiskCard.vue'

export default {
	name: 'DiskList',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		AlertIcon,
		HarddiskIcon,
		DiskCard,
	},
	props: {
		disks: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: null,
		},
	},
}
</script>
