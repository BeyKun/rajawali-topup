import auth from './auth'
import products from './products'
import orders from './orders'
const v1 = {
    auth: Object.assign(auth, auth),
products: Object.assign(products, products),
orders: Object.assign(orders, orders),
}

export default v1