const pendingRequests = new Map()

/**
 * Calls with the same key that run at the same time share one request, e.g. when every organization of a list mounts
 * the addon at once. A later call sends a new request, so the data is never older than the running request.
 *
 * @param {string} key
 * @param {function(): Promise} request
 * @return {Promise}
 */
export function shareRequest (key, request) {
  if (!pendingRequests.has(key)) {
    pendingRequests.set(key, request().finally(() => pendingRequests.delete(key)))
  }

  return pendingRequests.get(key)
}
