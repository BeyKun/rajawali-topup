import auth from './auth'
import products from './products'
import banners from './banners'
import promos from './promos'
import regions from './regions'
import profile from './profile'
import orders from './orders'
import wa from './wa'
const v1 = {
    auth: Object.assign(auth, auth),
products: Object.assign(products, products),
banners: Object.assign(banners, banners),
promos: Object.assign(promos, promos),
regions: Object.assign(regions, regions),
profile: Object.assign(profile, profile),
orders: Object.assign(orders, orders),
wa: Object.assign(wa, wa),
}

export default v1