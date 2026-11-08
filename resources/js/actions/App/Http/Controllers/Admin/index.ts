import DashboardController from './DashboardController'
import ProductController from './ProductController'
import VoucherController from './VoucherController'
import VoucherOcrController from './VoucherOcrController'
import OutletUserController from './OutletUserController'
import OrderController from './OrderController'
import RegionController from './RegionController'
import PromoController from './PromoController'
import PromoBannerController from './PromoBannerController'
import PromoVoucherController from './PromoVoucherController'
import KabupatenAdminController from './KabupatenAdminController'
import TelkomselAreaController from './TelkomselAreaController'
const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
ProductController: Object.assign(ProductController, ProductController),
VoucherController: Object.assign(VoucherController, VoucherController),
VoucherOcrController: Object.assign(VoucherOcrController, VoucherOcrController),
OutletUserController: Object.assign(OutletUserController, OutletUserController),
OrderController: Object.assign(OrderController, OrderController),
RegionController: Object.assign(RegionController, RegionController),
PromoController: Object.assign(PromoController, PromoController),
PromoBannerController: Object.assign(PromoBannerController, PromoBannerController),
PromoVoucherController: Object.assign(PromoVoucherController, PromoVoucherController),
KabupatenAdminController: Object.assign(KabupatenAdminController, KabupatenAdminController),
TelkomselAreaController: Object.assign(TelkomselAreaController, TelkomselAreaController),
}

export default Admin