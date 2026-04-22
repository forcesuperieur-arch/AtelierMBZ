export default defineNuxtRouteMiddleware(() => {
  if (!import.meta.client) return

  const { protocol, hostname, port, pathname, search, hash } = window.location
  const isLocalHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]'

  // Local HTTPS is currently unstable in this Docker dev stack; force HTTP locally.
  if (protocol === 'https:' && isLocalHost) {
    const httpPort = port && port !== '443' ? `:${port}` : ''
    window.location.replace(`http://${hostname}${httpPort}${pathname}${search}${hash}`)
  }
})