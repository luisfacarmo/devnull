/**
 * Transport factory.
 *
 * Returns the best available transport. Currently only PollingTransport.
 * When SSE is implemented, this factory will negotiate with the server
 * and return SSETransport when supported.
 */

import { PollingTransport } from './PollingTransport.js'

/**
 * Create the appropriate status transport.
 * @param {Object} [options]
 * @param {number} [options.interval=5000] Polling interval in ms
 * @returns {import('./StatusTransport.js').TransportInterface}
 */
export function createTransport(options = {}) {
	// Future: check server capabilities for SSE support
	// if (serverSupportsSSE) return new SSETransport()

	return new PollingTransport(options.interval ?? 5000)
}
