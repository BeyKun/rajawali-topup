import v1 from './v1'
import webhooks from './webhooks'
const api = {
    v1: Object.assign(v1, v1),
webhooks: Object.assign(webhooks, webhooks),
}

export default api