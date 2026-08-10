/**
 * PollingTransport — Status updates via periodic HTTP polling.
 *
 * Features:
 * - Configurable interval (default 5s)
 * - Exponential backoff on errors (up to 30s)
 * - Resets to normal interval on success
 * - Pauses when tab is not visible (saves resources)
 */

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { TransportInterface } from './StatusTransport.js'

const DEFAULT_INTERVAL = 5000     // 5 seconds
const MAX_INTERVAL = 30000        // 30 seconds max backoff
const BACKOFF_MULTIPLIER = 2

export class PollingTransport extends TransportInterface {
	constructor(interval = DEFAULT_INTERVAL) {
		super()
		this._baseInterval = interval
		this._currentInterval = interval
		this._timerId = null
		this._callback = null
		this._paused = false

		// Pause when tab is hidden
		this._visibilityHandler = () => {
			if (document.hidden) {
				this._pause()
			} else {
				this._resume()
			}
		}
	}

	subscribe(callback) {
		this._callback = callback
		document.addEventListener('visibilitychange', this._visibilityHandler)
		this._poll() // immediate first call
		this._schedule()
	}

	unsubscribe() {
		this._callback = null
		document.removeEventListener('visibilitychange', this._visibilityHandler)
		if (this._timerId) {
			clearTimeout(this._timerId)
			this._timerId = null
		}
	}

	getType() {
		return 'polling'
	}

	_schedule() {
		if (this._timerId) {
			clearTimeout(this._timerId)
		}
		this._timerId = setTimeout(() => {
			if (!this._paused) {
				this._poll()
			}
			this._schedule()
		}, this._currentInterval)
	}

	async _poll() {
		if (!this._callback) return

		try {
			const url = generateOcsUrl('/apps/devnull/api/v1/status')
			const response = await axios.get(url)
			const status = response.data.ocs?.data?.status ?? {}

			this._callback(status)

			// Reset interval on success
			this._currentInterval = this._baseInterval
		} catch (e) {
			// Exponential backoff on failure
			this._currentInterval = Math.min(
				this._currentInterval * BACKOFF_MULTIPLIER,
				MAX_INTERVAL,
			)
			console.warn(`DevNull: status poll failed, backing off to ${this._currentInterval}ms`)
		}
	}

	_pause() {
		this._paused = true
	}

	_resume() {
		this._paused = false
		this._poll() // immediate refresh on tab focus
	}
}
