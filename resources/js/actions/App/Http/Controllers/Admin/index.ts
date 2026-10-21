import DashboardController from './DashboardController'
import ProductController from './ProductController'
import VoucherController from './VoucherController'
import OutletUserController from './OutletUserController'
import PromoController from './PromoController'
import PromoBannerController from './PromoBannerController'
import PromoVoucherController from './PromoVoucherController'
import OrderController from './OrderController'
import RegionController from './RegionController'
import KabupatenAdminController from './KabupatenAdminController'
import TelkomselAreaController from './TelkomselAreaController'
const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
ProductController: Object.assign(ProductController, ProductController),
VoucherController: Object.assign(VoucherController, VoucherController),
OutletUserController: Object.assign(OutletUserController, OutletUserController),
PromoController: Object.assign(PromoController, PromoController),
PromoBannerController: Object.assign(PromoBannerController, PromoBannerController),
PromoVoucherController: Object.assign(PromoVoucherController, PromoVoucherController),
OrderController: Object.assign(OrderController, OrderController),
RegionController: Object.assign(RegionController, RegionController),
KabupatenAdminController: Object.assign(KabupatenAdminController, KabupatenAdminController),
TelkomselAreaController: Object.assign(TelkomselAreaController, TelkomselAreaController),
}

export default Admin