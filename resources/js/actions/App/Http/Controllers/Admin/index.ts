import DashboardController from './DashboardController'
import ProductController from './ProductController'
import VoucherController from './VoucherController'
import OrderController from './OrderController'
const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
ProductController: Object.assign(ProductController, ProductController),
VoucherController: Object.assign(VoucherController, VoucherController),
OrderController: Object.assign(OrderController, OrderController),
}

export default Admin