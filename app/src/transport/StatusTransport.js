/**
 * StatusTransport — Abstract interface for status updates.
 *
 * Implementations:
 * - PollingTransport (MVP): polls GET /api/v1/status every N seconds
 * - SSETransport (future): Server-Sent Events stream
 *
 * Usage:
 *   const transport = createTransport()
 *   transport.subscribe((status) => { ... })
 *   transport.unsubscribe()
 */

/**
 * @typedef {Object} StatusPayload
 * @property {Array} mounts - Active mounts
 * @property {Array} operations - Running operations
 * @property {number} timestamp - Server timestamp
 */

/**
 * @typedef {Object} StatusTransport
 * @property {function(function(StatusPayload): void): void} subscribe
 * @property {function(): void} unsubscribe
 * @property {function(): string} getType
 */

export class TransportInterface {
	/**
	 * Start receiving status updates.
	 * @param {function(StatusPayload): void} callback
	 */
	subscribe(callback) {
		throw new Error('subscribe() must be implemented')
	}

	/**
	 * Stop receiving updates and clean up.
	 */
	unsubscribe() {
		throw new Error('unsubscribe() must be implemented')
	}

	/**
	 * @returns {string} Transport type identifier
	 */
	getType() {
		throw new Error('getType() must be implemented')
	}
}
