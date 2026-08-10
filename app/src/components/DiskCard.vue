<template>
	<div class="devnull-disk-card">
		<div class="devnull-disk-card__header">
			<HarddiskIcon :size="24" />
			<span class="devnull-disk-card__name">
				{{ disk.label || disk.name }}
			</span>
		</div>

		<div class="devnull-disk-card__details">
			<span>{{ t('devnull', 'Device') }}</span>
			<span>/dev/{{ disk.name }}</span>

			<span>{{ t('devnull', 'Size') }}</span>
			<span>{{ disk.size }}</span>

			<span>{{ t('devnull', 'Filesystem') }}</span>
			<span>{{ disk.fstype || t('devnull', 'Unknown') }}</span>

			<span v-if="disk.model">{{ t('devnull', 'Model') }}</span>
			<span v-if="disk.model">{{ disk.model }}</span>

			<span v-if="disk.serial">{{ t('devnull', 'Serial') }}</span>
			<span v-if="disk.serial">{{ disk.serial }}</span>
		</div>

		<div class="devnull-disk-card__actions">
			<NcButton v-if="!disk.mounted"
				type="primary"
				@click="$emit('mount')">
				<template #icon>
					<PlayIcon :size="20" />
				</template>
				{{ t('devnull', 'Mount') }}
			</NcButton>

			<NcButton v-else
				type="error"
				@click="$emit('unmount')">
				<template #icon>
					<EjectIcon :size="20" />
				</template>
				{{ t('devnull', 'Eject') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import HarddiskIcon from 'vue-material-design-icons/Harddisk.vue'
import PlayIcon from 'vue-material-design-icons/Play.vue'
import EjectIcon from 'vue-material-design-icons/Eject.vue'

export default {
	name: 'DiskCard',
	components: {
		NcButton,
		HarddiskIcon,
		PlayIcon,
		EjectIcon,
	},
	props: {
		disk: {
			type: Object,
			required: true,
		},
	},
}
</script>
