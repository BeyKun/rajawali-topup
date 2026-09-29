import Auth from './Auth'
import ProductController from './ProductController'
import OrderController from './OrderController'
import WebhookController from './WebhookController'
const Api = {
    Auth: Object.assign(Auth, Auth),
ProductController: Object.assign(ProductController, ProductController),
OrderController: Object.assign(OrderController, OrderController),
WebhookController: Object.assign(WebhookController, WebhookController),
}

export default Api