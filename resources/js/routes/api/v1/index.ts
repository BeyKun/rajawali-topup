import auth from './auth'
import products from './products'
import regions from './regions'
import profile from './profile'
import orders from './orders'
import wa from './wa'
const v1 = {
    auth: Object.assign(auth, auth),
products: Object.assign(products, products),
regions: Object.assign(regions, regions),
profile: Object.assign(profile, profile),
orders: Object.assign(orders, orders),
wa: Object.assign(wa, wa),
}

export default v1