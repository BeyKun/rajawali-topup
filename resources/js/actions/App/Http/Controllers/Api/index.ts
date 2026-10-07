import Auth from './Auth'
import ProductController from './ProductController'
import RegionController from './RegionController'
import OutletProfileController from './OutletProfileController'
import OrderController from './OrderController'
import WebhookController from './WebhookController'
import WhatsApp from './WhatsApp'
const Api = {
    Auth: Object.assign(Auth, Auth),
ProductController: Object.assign(ProductController, ProductController),
RegionController: Object.assign(RegionController, RegionController),
OutletProfileController: Object.assign(OutletProfileController, OutletProfileController),
OrderController: Object.assign(OrderController, OrderController),
WebhookController: Object.assign(WebhookController, WebhookController),
WhatsApp: Object.assign(WhatsApp, WhatsApp),
}

export default Api